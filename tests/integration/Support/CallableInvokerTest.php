<?php

declare(strict_types=1);

namespace Tests\Integration\Support;

use ArgumentCountError;
use Maduser\Argon\Container\Contracts\ArgumentResolverInterface;
use Maduser\Argon\Container\Contracts\ServiceResolverInterface;
use Maduser\Argon\Container\Exceptions\ContainerException;
use Maduser\Argon\Container\Exceptions\NotFoundException;
use Maduser\Argon\Container\Support\CallableInvoker;
use Maduser\Argon\Container\Support\CallableWrapper;
use Maduser\Argon\Container\Support\ReflectionUtils;
use Maduser\Argon\Container\Support\ServiceInvoker;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionParameter;

final class CallableInvokerTest extends TestCase
{
    private CallableInvoker $invoker;

    #[\Override]
    protected function setUp(): void
    {
        $serviceResolver = new class implements ServiceResolverInterface {
            #[\Override]
            public function resolve(string $id, array $args = []): object
            {
                if ($id === 'test') {
                    return new class {
                        public function __invoke(): string
                        {
                            return 'works';
                        }
                    };
                }

                throw new \RuntimeException("Service not found: $id");
            }

            public function setStrictMode(bool $strict): void
            {
                // noop for tests
            }
        };

        $argumentResolver = new class implements ArgumentResolverInterface {
            #[\Override]
            public function resolve(ReflectionParameter $param, array $overrides = [], ?string $contextId = null): mixed
            {
                return $overrides[$param->getName()] ?? null;
            }

            #[\Override]
            public function setServiceResolver(ServiceResolverInterface $resolver): void
            {
            }
        };

        $this->invoker = new CallableInvoker($serviceResolver, $argumentResolver);
    }

    /**
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function testClosureThrowsExceptionIsBubbled(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Kaboom');

        $this->invoker->call(fn() => throw new \RuntimeException('Kaboom'));
    }

    /**
     * @throws NotFoundException
     */
    public function testFailsOnUnresolvableTarget(): void
    {
        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage('Cannot resolve callable');

        $this->invoker->call('not-a-callable');
    }

    /**
     * @throws NotFoundException
     */
    public function testFailsOnInvalidMethod(): void
    {
        $this->expectException(ContainerException::class);
        $this->expectExceptionMessageMatches('/Failed to reflect callable/');

        $this->invoker->call([new class {
        }, 'doesNotExist']);
    }

    /**
     * @throws \ReflectionException
     */
    public function testThrowsOnFailedInvokeDueToArgumentCount(): void
    {
        $obj = new class {
            public function needsParam(string $required): void
            {
            }
        };

        $reflection = new ReflectionMethod($obj, 'needsParam');
        $wrapper = new CallableWrapper($obj, $reflection);

        $invokerReflection = new ReflectionClass($this->invoker);
        $method = $invokerReflection->getMethod('invokeCallable');

        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage("Failed to instantiate 'needsParam' with resolved dependencies.");

        $method->invoke($this->invoker, $wrapper, []);
    }
}
