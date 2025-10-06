<?php

declare(strict_types=1);

namespace Tests\Integration;

use Maduser\Argon\Container\Container;
use Maduser\Argon\Container\Contracts\PostResolutionInterceptorInterface;
use Maduser\Argon\Container\Exceptions\ContainerException;
use Maduser\Argon\Container\Exceptions\NotFoundException;
use Maduser\Argon\Container\ArgonContainer;
use PHPUnit\Framework\TestCase;
use stdClass;
use Tests\Integration\Mocks\A;
use Tests\Integration\Mocks\B;
use Tests\Integration\Mocks\C;
use Tests\Integration\Mocks\Logger;
use Tests\Integration\Mocks\UsesLogger;

final class ServiceContainerTest extends TestCase
{
    private ArgonContainer $container;

    protected function setUp(): void
    {
        $this->container = new ArgonContainer();
    }

    /**
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function testContainerIsAlwaysItselfAndProtected(): void
    {
        $container = new ArgonContainer();

        // 1. Self resolution works
        $this->assertSame($container, $container->get(ArgonContainer::class));

        // 2. It cannot be bound manually
        $this->expectException(ContainerException::class);
        $container->set(ArgonContainer::class, fn () => new ArgonContainer());

        // 3. It cannot be bound as singleton either
        try {
            $container->set(ArgonContainer::class, fn () => new ArgonContainer());
            $this->fail('Binding ArgonContainer::class should throw an exception.');
        } catch (ContainerException $e) {
            $this->assertStringContainsString('maniac', $e->getMessage());
        }
    }

    /**
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function testInvokeClosureWithDependency(): void
    {
        $this->container->set(Logger::class);

        $result = $this->container->invoke(function (Logger $logger): string {
            return get_class($logger);
        });

        $this->assertSame(Logger::class, $result);
    }

    /**
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function testInvokeMethodOnResolvedClass(): void
    {
        $this->container->set(Logger::class);

        $result = $this->container->invoke([UsesLogger::class, 'reportSomething']);

        $this->assertSame('Reported by logger', $result);
    }

    /**
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function testInvokeWithParameterOverride(): void
    {
        $result = $this->container->invoke(
            fn(string $message): string => "Message: $message",
            arguments: ['message' => 'Overridden']
        );

        $this->assertSame('Message: Overridden', $result);
    }

    /**
     * @throws NotFoundException
     * @throws ContainerException
     */
    public function testInvokeWithInvalidCallableThrows(): void
    {
        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage("Cannot resolve callable.");

        $this->container->invoke('this_is_not_callable');
    }

    /**
     * @throws ContainerException
     */
    public function testStrictModeDisallowsUnregisteredAutowiring(): void
    {
        $container = new ArgonContainer(strictMode: true);

        $this->expectException(NotFoundException::class);
        $container->get(Logger::class);
    }

    /**
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function testStrictModeResolvesRegisteredService(): void
    {
        $container = new ArgonContainer(strictMode: true);
        $container->set(Logger::class);

        $this->assertInstanceOf(Logger::class, $container->get(Logger::class));
    }

    /**
     * @throws ContainerException
     */
    public function testInvokeThrowsWhenResolutionFails(): void
    {
        $this->expectException(NotFoundException::class);
        $this->container->invoke(['NonExistentService', 'method']);
    }

    /**
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function testPostInterceptorModifiesResolvedInstance(): void
    {
        // Define a concrete interceptor class inline for clarity/testing
        $interceptor = new class implements PostResolutionInterceptorInterface {
            public static function supports(object|string $target): bool
            {
                return $target === stdClass::class || $target instanceof stdClass;
            }

            public function intercept(object $instance): void
            {
                $instance->intercepted = true;
            }
        };

        // Register interceptor as FQCN (as expected now)
        $container = new ArgonContainer();
        $container->registerInterceptor(get_class($interceptor));

        // Bind a service (autowiring would also work)
        $container->set('service', fn() => new \stdClass());

        // Resolve the service
        $instance = $container->get('service');

        // Assertion
        $this->assertTrue($instance->intercepted ?? false, 'Service instance should be intercepted.');
    }

    public function testSingletonSelfBindingThrowsException(): void
    {
        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage("Don't bind the container to itself, you maniac.");

        $container = new ArgonContainer();
        $container->set(ArgonContainer::class);
    }

    public function testBindSelfBindingThrowsException(): void
    {
        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage("Don't bind the container to itself, you maniac.");

        $container = new ArgonContainer();
        $container->set(ArgonContainer::class);
    }

    /**
     * @throws ContainerException
     */
    public function testGetTaggedIdsReturnsCorrectServiceIds(): void
    {
        $container = new ArgonContainer();

        $container->set(A::class)->tag(['foo']);
        $container->set(B::class)->tag(['foo', 'bar']);
        $container->set(C::class)->tag(['bar']);

        $fooTagged = $container->getTaggedIds('foo');
        $barTagged = $container->getTaggedIds('bar');
        $nonTagged = $container->getTaggedIds('baz');

        $this->assertSame([A::class, B::class], $fooTagged, 'Expected foo tag to contain service.a and service.b');
        $this->assertSame([B::class, C::class], $barTagged, 'Expected bar tag to contain service.b and service.c');
        $this->assertSame([], $nonTagged, 'Expected unknown tag to return an empty array');
    }
}
