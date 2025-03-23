<?php

declare(strict_types=1);

namespace Tests\Unit\Container;

use Maduser\Argon\Container\Exceptions\ContainerException;
use Maduser\Argon\Container\Exceptions\NotFoundException;
use Maduser\Argon\Container\ServiceContainer;
use PHPUnit\Framework\TestCase;
use ReflectionException;
use Tests\Mocks\DatabaseLogger;
use Tests\Mocks\FileLogger;
use Tests\Mocks\LoggerInterface;
use Tests\Mocks\ServiceA;
use Tests\Mocks\ServiceB;

class ContextualBindingTest extends TestCase
{
    /**
     * @throws ContainerException
     * @throws NotFoundException
     * @throws ReflectionException
     */
    public function testContextualBindingWithClassString(): void
    {
        $container = new ServiceContainer();

        $container->bind(FileLogger::class);
        $container->bind(DatabaseLogger::class);
        $container->bind(ServiceA::class);
        $container->bind(ServiceB::class);

        $container->for(ServiceA::class)->set(LoggerInterface::class, FileLogger::class);
        $container->for(ServiceB::class)->set(LoggerInterface::class, DatabaseLogger::class);

        $a = $container->get(ServiceA::class);
        $b = $container->get(ServiceB::class);

        $this->assertInstanceOf(FileLogger::class, $a->logger);
        $this->assertInstanceOf(DatabaseLogger::class, $b->logger);
    }

    /**
     * @throws ReflectionException
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function testContextualBindingWithClosure(): void
    {
        $container = new ServiceContainer();

        $container->bind(ServiceA::class);

        $container->for(ServiceA::class)->set(LoggerInterface::class, function (): LoggerInterface {
            return new FileLogger();
        });

        $a = $container->get(ServiceA::class);

        $this->assertInstanceOf(FileLogger::class, $a->logger);
    }
}
