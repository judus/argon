<?php

declare(strict_types=1);

namespace Tests\Unit\Container;

use Closure;
use Maduser\Argon\Container\CallableInvoker;
use Maduser\Argon\Container\Contracts\CallableWrapperInterface;
use Maduser\Argon\Container\Contracts\ArgumentResolverInterface;
use Maduser\Argon\Container\Contracts\ServiceResolverInterface;
use Maduser\Argon\Container\Exceptions\ContainerException;
use Maduser\Argon\Container\Exceptions\NotFoundException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionException;
use stdClass;

class CallableInvokerTest extends TestCase
{
    /** @psalm-suppress PropertyNotSetInConstructor */
    private ServiceResolverInterface&MockObject $resolver;
    /** @psalm-suppress PropertyNotSetInConstructor */
    private ArgumentResolverInterface&MockObject $parameterResolver;
    /** @psalm-suppress PropertyNotSetInConstructor */
    private CallableInvoker $invoker;

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

        $result = $this->invoker->call($obj, 'sayHello');
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

        $result = $this->invoker->call('MyService', 'foo');
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
        $this->expectExceptionMessage('Unsupported callable type');

        // This string won't resolve to a valid service or callable
        $this->invoker->call('not_a_callable_string', null, []);
    }

    /**
     * @throws NotFoundException
     */
    public function testThrowsForReflectionFailure(): void
    {
        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage('Failed to reflect callable');

        $this->invoker->call('NonExistentClass', 'nope');
    }

    /**
     * @throws NotFoundException
     */
    public function testThrowsOnReflectionInvokeFailure(): void
    {
        $failing = new class {
            public function thrower(): void
            {
                throw new \RuntimeException("Boom");
            }
        };

        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage("Failed to instantiate 'thrower' with resolved dependencies.");

        // This forces the invoker to use ReflectionMethod
        $this->invoker->call($failing, 'thrower');
    }
}
