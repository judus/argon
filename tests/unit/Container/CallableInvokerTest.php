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
     * @throws NotFoundException
     */
    public function testThrowsForReflectionFailure(): void
    {
        $this->expectException(ContainerException::class);
        $this->expectExceptionMessageMatches('/Failed to reflect callable/');

        $this->invoker->call(['NonExistentClass', 'nonExistentMethod']);
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
