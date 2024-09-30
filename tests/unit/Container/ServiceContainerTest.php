<?php

declare(strict_types=1);

namespace Tests\Unit\Container;

use Exception;
use Maduser\Argon\Container\ServiceContainer;
use PHPUnit\Framework\TestCase;
use stdClass;

class ServiceContainerTest extends TestCase
{
    protected ServiceContainer $container;

    /**
     * @throws Exception
     */
    public function testRegisterAndResolveService(): void
    {
        // Resolve the service and check if it's the expected class
        $service = $this->container->get('service');

        $this->assertInstanceOf(stdClass::class, $service);
    }

    /**
     * @throws Exception
     */
    public function testSingletonRegistration(): void
    {
        // Resolve the singleton twice and check if it's the same instance
        $resolvedSingleton1 = $this->container->get('singleton');
        $resolvedSingleton2 = $this->container->get('singleton');

        $this->assertSame($resolvedSingleton1, $resolvedSingleton2);
    }

    protected function setUp(): void
    {
        $this->container = new ServiceContainer();
        $this->container->set('service', stdClass::class);
        $this->container->singleton('singleton', stdClass::class);
    }
}
