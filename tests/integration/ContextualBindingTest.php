<?php

declare(strict_types=1);

namespace Tests\Integration;

use Maduser\Argon\Container\Exceptions\ContainerException;
use Maduser\Argon\Container\Exceptions\NotFoundException;
use Maduser\Argon\Container\ArgonContainer;
use PHPUnit\Framework\TestCase;
use Tests\Integration\Mocks\DatabaseLogger;
use Tests\Integration\Mocks\FileLogger;
use Tests\Integration\Mocks\LoggerInterface;
use Tests\Integration\Mocks\NullLogger;
use Tests\Integration\Mocks\ServiceA;
use Tests\Integration\Mocks\ServiceB;
use Tests\Integration\Mocks\UnboundConsumer;

class ContextualBindingTest extends TestCase
{
    /**
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function testDifferentConsumersGetDifferentImplementations(): void
    {
        $container = new ArgonContainer();

        $container->for(ServiceA::class)->bind(LoggerInterface::class, DatabaseLogger::class);
        $container->for(ServiceB::class)->bind(LoggerInterface::class, FileLogger::class);

        $a = $container->get(ServiceA::class);
        $b = $container->get(ServiceB::class);

        $this->assertInstanceOf(DatabaseLogger::class, $a->logger);
        $this->assertInstanceOf(FileLogger::class, $b->logger);
    }

    /**
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function testFallsBackToGlobalBindingWhenNoContextual(): void
    {
        $container = new ArgonContainer();

        $container->singleton(LoggerInterface::class, NullLogger::class);

        $instance = $container->get(UnboundConsumer::class);

        $this->assertInstanceOf(NullLogger::class, $instance->logger);
    }

    /**
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function testContextualBindingWithClassString(): void
    {
        $container = new ArgonContainer();

        $container->bind(FileLogger::class);
        $container->bind(DatabaseLogger::class);
        $container->bind(ServiceA::class);
        $container->bind(ServiceB::class);

        $container->for(ServiceA::class)->bind(LoggerInterface::class, FileLogger::class);
        $container->for(ServiceB::class)->bind(LoggerInterface::class, DatabaseLogger::class);

        $a = $container->get(ServiceA::class);
        $b = $container->get(ServiceB::class);

        $this->assertInstanceOf(FileLogger::class, $a->logger);
        $this->assertInstanceOf(DatabaseLogger::class, $b->logger);
    }

    /**
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function testContextualBindingWithClosure(): void
    {
        $container = new ArgonContainer();

        $container->bind(ServiceA::class);

        $container->for(ServiceA::class)->bind(LoggerInterface::class, function (): LoggerInterface {
            return new FileLogger();
        });

        $a = $container->get(ServiceA::class);

        $this->assertInstanceOf(FileLogger::class, $a->logger);
    }
}
