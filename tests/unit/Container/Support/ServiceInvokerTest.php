<?php

declare(strict_types=1);

namespace Tests\Unit\Container\Support;

use Maduser\Argon\Container\ArgonContainer;
use Maduser\Argon\Container\Exceptions\ContainerException;
use Maduser\Argon\Container\Exceptions\NotFoundException;
use Maduser\Argon\Container\Support\ServiceInvoker;
use Maduser\Argon\Container\Support\StringHelper;
use PHPUnit\Framework\TestCase;

final class ServiceInvokerTest extends TestCase
{
    /**
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function testCallsCompiledMethodIfExists(): void
    {
        $method = StringHelper::invokeServiceMethod('FakeService', 'handle');

        /** @template-extends ArgonContainer<mixed> */
        $container = new class ($method) extends ArgonContainer  {
            private string $compiledMethod;

            public function __construct(string $compiledMethod)
            {
                $this->compiledMethod = $compiledMethod;
            }

            #[\Override]
            public function invoke(callable|string|array|object $target, array $arguments = []): mixed
            {
                return 'dynamic-fallback';
            }

            // @phpcs:disable
            public function invoke_FakeService__handle(array $arguments): string
            {
                return 'compiled-hit';
            }
            // @phpcs:enable
        };

        /**
         * @psalm-suppress UndefinedClass
         * @psalm-suppress ArgumentTypeCoercion
         */
        $invoker = new ServiceInvoker($container, 'FakeService', 'handle');
        $result = $invoker();

        $this->assertSame('compiled-hit', $result);
    }

    /**
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function testFallsBackToDynamicInvocation(): void
    {
        $container = new class extends ArgonContainer {
            #[\Override]
            public function invoke(callable|string|array|object $target, array $arguments = []): mixed
            {
                return 'fallback-hit';
            }
        };

        /**
         * @psalm-suppress UndefinedClass
         * @psalm-suppress ArgumentTypeCoercion
         */
        $invoker = new ServiceInvoker($container, 'SomeService', 'run');
        $result = $invoker();

        $this->assertSame('fallback-hit', $result);
    }

    /**
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function testFallsBackToInvokeWhenCompiledMethodMissing(): void
    {
        $container = $this->getMockBuilder(ArgonContainer::class)
            ->onlyMethods(['invoke'])
            ->getMock();

        $container->expects($this->once())
            ->method('invoke')
            ->with(['OtherService', 'handle'], ['x' => 123])
            ->willReturn('fallback-ok');

        /**
         * @psalm-suppress UndefinedClass
         * @psalm-suppress ArgumentTypeCoercion
         */
        $invoker = new ServiceInvoker($container, 'OtherService', 'handle');

        $result = $invoker(['x' => 123]);

        $this->assertSame('fallback-ok', $result);
    }
}
