<?php

declare(strict_types=1);

namespace Tests\Unit\Container;

use Maduser\Argon\Container\Contracts\ServiceProviderInterface;
use Maduser\Argon\Container\Exceptions\ContainerException;
use Maduser\Argon\Container\Exceptions\NotFoundException;
use Maduser\Argon\Container\ServiceContainer;
use PHPUnit\Framework\TestCase;
use ReflectionException;
use stdClass;
use Tests\Mocks\DummyProvider;
use Tests\Mocks\MyService;

class ServiceProviderTest extends TestCase
{
    /**
     * @throws ContainerException
     * @throws NotFoundException
     * @throws ReflectionException
     */
    public function testServiceProviderIsRegisteredAndTagged(): void
    {
        $container = new ServiceContainer();
        $container->registerServiceProvider(DummyProvider::class);

        // The provider should be registered as a service
        $this->assertTrue($container->has(DummyProvider::class));

        // The provider should be tagged as 'service.provider'
        $tagged = $container->getTagged('service.provider');
        $this->assertNotEmpty($tagged);
        $this->assertInstanceOf(DummyProvider::class, $tagged[0]);

        // The service it registers should also exist
        $this->assertTrue($container->has('dummy.service'));
        $this->assertInstanceOf(stdClass::class, $container->get('dummy.service'));
    }

    /**
     * @throws ReflectionException
     * @throws NotFoundException
     */
    public function testInvalidServiceProviderThrowsException(): void
    {
        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage("must implement ServiceProviderInterface");

        $container = new ServiceContainer();
        $container->registerServiceProvider(MyService::class);
    }
}
