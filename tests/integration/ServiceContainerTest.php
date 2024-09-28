<?php

declare(strict_types=1);

namespace Maduser\Argon\Tests\Integration;

use Maduser\Argon\Mocks\SingletonObject;
use Maduser\Argon\Container\ServiceContainer;
use PHPUnit\Framework\TestCase;

class ServiceContainerTest extends TestCase
{
    public function testRegisterAndResolveService(): void
    {
        // Create a new container
        $container = new ServiceContainer();

        // Register a service
        $container->register('testService', \stdClass::class);

        // Resolve the service and check if it's the expected class
        $service = $container->resolve('testService');

        $this->assertInstanceOf(\stdClass::class, $service);
    }

    public function testSingletonRegistration(): void
    {
        // Create a new container
        $container = new ServiceContainer();

        // Register a singleton
        $container->singleton('testSingleton', SingletonObject::class);

        // Resolve the singleton and check if it's the same instance
        $resolvedSingleton1 = $container->resolve('testSingleton');
        $resolvedSingleton2 = $container->resolve('testSingleton');

        $this->assertSame($resolvedSingleton1, $resolvedSingleton2);
    }
}