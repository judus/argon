<?php

declare(strict_types=1);

namespace Tests\Unit\Container;

use Maduser\Argon\Container\Contracts\ArgumentResolverInterface;
use Maduser\Argon\Container\Contracts\ServiceResolverInterface;
use Maduser\Argon\Container\Exceptions\ContainerException;
use Maduser\Argon\Container\Exceptions\NotFoundException;
use Maduser\Argon\Container\Support\CallableInvoker;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionParameter;
use RuntimeException;
use TypeError;

final class CallableInvokerTest extends TestCase
{
    /** @psalm-suppress PropertyNotSetInConstructor */
    private ServiceResolverInterface&MockObject $resolver;
    /** @psalm-suppress PropertyNotSetInConstructor */
    private ArgumentResolverInterface&MockObject $parameterResolver;
    /** @psalm-suppress PropertyNotSetInConstructor */
    private CallableInvoker $invoker;

    #[\Override]
    protected function setUp(): void
    {
        $this->resolver = $this->createMock(ServiceResolverInterface::class);
        $this->parameterResolver = $this->createMock(ArgumentResolverInterface::class);
        $this->invoker = new CallableInvoker($this->resolver, $this->parameterResolver);
    }

    /**
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function testClosureInvocation(): void
    {
        $result = $this->invoker->call(fn() => 'worked');
        $this->assertSame('worked', $result);
    }

    /**
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function testObjectMethodInvocation(): void
    {
        $obj = new class {
            public function sayHello(): string
            {
                return 'hello';
            }
        };

        $result = $this->invoker->call([$obj, 'sayHello']);
        $this->assertSame('hello', $result);
    }

    /**
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function testClassMethodResolutionViaServiceResolver(): void
    {
        $instance = new class {
            public function foo(): string
            {
                return 'bar';
            }
        };

        $this->resolver->method('resolve')->with('MyService')->willReturn($instance);

        $result = $this->invoker->call(['MyService', 'foo']);
        $this->assertSame('bar', $result);
    }

    /**
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function testParametersAreResolved(): void
    {
        $closure = function (string $name): string {
            return "Hello, $name";
        };

        $this->parameterResolver->method('resolve')->willReturn('World');

        $result = $this->invoker->call($closure);
        $this->assertSame('Hello, World', $result);
    }

    /**
     * @throws NotFoundException
     */
    public function testThrowsForUnsupportedCallableType(): void
    {
        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage('Cannot resolve callable.');

        // This string won't resolve to a valid service or callable
        $this->invoker->call('not_a_callable_string', []);
    }

    /**
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function testResolvesPlainStringFunctionCallable(): void
    {
        $this->parameterResolver->method('resolve')->willReturnCallback(
            static function (ReflectionParameter $param, array $overrides = []): mixed {
                $name = $param->getName();

                /** @var array<string, mixed> $overrides */
                return array_key_exists($name, $overrides) ? $overrides[$name] : null;
            }
        );

        $result = $this->invoker->call('strtoupper', ['string' => 'foo']);

        $this->assertSame('FOO', $result);
    }

    /**
     * @throws NotFoundException
     * @throws ContainerException
     */
    public function testFailsWithNativeTypeErrorWhenNoArgsProvided(): void
    {
        $failing = new class {
            public function thrower(string $unresolvable): void
            {
            }
        };

        $this->expectException(TypeError::class);
        $this->expectExceptionMessageMatches('/must be of type string, null given/');

        $this->invoker->call([$failing, 'thrower']);
    }

    /**
     * @throws NotFoundException
     * @throws ContainerException
     */
    public function testThrowsOnUnresolvableDependency(): void
    {
        $failing = new class {
            public function thrower(string $unresolvable): void
            {
            }
        };

        $this->expectException(TypeError::class);
        $this->expectExceptionMessageMatches('/must be of type string, null given/');

        $this->invoker->call([$failing, 'thrower']);
    }

    /**
     * @throws NotFoundException
     * @throws ContainerException
     */
    public function testThrowsOnReflectionInvokeFailure(): void
    {
        $failing = new class {
            public function thrower(): void
            {
                throw new RuntimeException("Boom!");
            }
        };

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Boom!");

        $this->invoker->call([$failing, 'thrower']);
    }

    public function testInvokableClassResolvedByServiceResolver(): void
    {
        // Define a real invokable class
        $className = new class {
            public function __invoke(): string
            {
                return 'invoked';
            }
        };

        // Create a unique name for this anonymous class
        $fauxClassName = get_class($className);

        // Mock resolver to return it when asked for this class
        $this->resolver->method('resolve')->with($fauxClassName)->willReturn($className);

        // Invoke by class name (string)
        $result = $this->invoker->call($fauxClassName);
        $this->assertSame('invoked', $result);
    }

    public function testStaticStringCallableResolution(): void
    {
        $instance = new class {
            public function foo(): string
            {
                return 'from method';
            }
        };

        $this->resolver->method('resolve')->with('SomeClass')->willReturn($instance);

        $result = $this->invoker->call('SomeClass::foo');
        $this->assertSame('from method', $result);
    }

    public function testDirectCallableObjectIsInvoked(): void
    {
        $callable = new class {
            public function __invoke(): string
            {
                return 'direct-callable';
            }
        };

        $result = $this->invoker->call($callable);

        $this->assertSame('direct-callable', $result);
    }
}
