<?php

declare(strict_types=1);

namespace Maduser\Argon\Tests\Unit\Container;

use Maduser\Argon\Container\Provider;
use PHPUnit\Framework\TestCase;

class ProviderTest extends TestCase
{
    public function testRegisterAndResolveService(): void
    {
        // Create a new provider
        $provider = new Provider();

        // Register a service
        $provider->register('testService', \stdClass::class);

        // Resolve the service and check if it's the expected class
        $service = $provider->resolve('testService');

        $this->assertInstanceOf(\stdClass::class, $service);
    }

    public function testSingletonRegistration(): void
    {
        // Create a new provider
        $provider = new Provider();

        // Register a singleton
        $singletonInstance = new \stdClass();
        $provider->singleton('testSingleton', $singletonInstance);

        // Resolve the singleton and check if it's the same instance
        $resolvedSingleton = $provider->resolve('testSingleton');

        $this->assertSame($singletonInstance, $resolvedSingleton);
    }
}