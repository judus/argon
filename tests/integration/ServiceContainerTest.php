<?php

declare(strict_types=1);

namespace Tests\Integration;

use Maduser\Argon\Container\Container;
use Maduser\Argon\Container\Exceptions\ContainerException;
use Maduser\Argon\Container\Exceptions\NotFoundException;
use Maduser\Argon\Container\ServiceContainer;
use PHPUnit\Framework\TestCase;
use Tests\Integration\Mocks\Logger;
use Tests\Integration\Mocks\UsesLogger;

final class ServiceContainerTest extends TestCase
{
    private ServiceContainer $container;

    protected function setUp(): void
    {
        $this->container = new ServiceContainer();
        Container::set($this->container);
    }

    /**
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function testInvokeClosureWithDependency(): void
    {
        $this->container->singleton(Logger::class);

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
        $this->container->singleton(Logger::class);

        $result = $this->container->invoke(UsesLogger::class, 'reportSomething');

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
            parameters: ['message' => 'Overridden']
        );

        $this->assertSame('Message: Overridden', $result);
    }

    /**
     * @throws NotFoundException
     * @throws ContainerException
     */
    public function testInvokeWithInvalidCallableThrows(): void
    {
        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage("Service 'this_is_not_callable' not found.");

        $this->container->invoke('this_is_not_callable');
    }

    /**
     * @throws ContainerException
     */
    public function testInvokeThrowsWhenResolutionFails(): void
    {
        $this->expectException(NotFoundException::class);
        $this->container->invoke('NonExistentService', 'method');
    }
}
