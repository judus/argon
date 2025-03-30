<?php

declare(strict_types=1);

namespace Tests\Unit\Container;

use Maduser\Argon\Container\Contracts\ServiceProviderInterface;
use Maduser\Argon\Container\Exceptions\ContainerException;
use Maduser\Argon\Container\Exceptions\NotFoundException;
use Maduser\Argon\Container\ArgonContainer;
use Maduser\Argon\Container\ServiceBinder;
use Maduser\Argon\Container\ServiceProviderRegistry;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use stdClass;
use Tests\Unit\Container\Mocks\SampleProvider;

class ServiceProviderRegistryTest extends TestCase
{
    /** @psalm-suppress PropertyNotSetInConstructor */
    private ArgonContainer&MockObject $container;

    protected function setUp(): void
    {
        parent::setUp();
        $this->container = $this->createMock(ArgonContainer::class);
    }

    /**
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function testRegisterValidProvider(): void
    {
        $binder = new ServiceBinder();
        $this->container = $this->getMockBuilder(ArgonContainer::class)
            ->onlyMethods(['singleton', 'tag', 'get'])
            ->setConstructorArgs([ 'binder' => $binder ])
            ->getMock();

        $providerClass = SampleProvider::class;
        $providerMock = $this->createMock(SampleProvider::class);

        // Return real binding builder (safe even though it's final)
        $bindingBuilder = $binder->singleton($providerClass);

        $this->container->expects($this->once())
            ->method('singleton')
            ->with($providerClass)
            ->willReturn($bindingBuilder);

        $this->container->expects($this->once())
            ->method('tag')
            ->with($providerClass, ['service.provider']);

        $this->container->expects($this->once())
            ->method('get')
            ->with($providerClass)
            ->willReturn($providerMock);

        $providerMock->expects($this->once())
            ->method('register')
            ->with($this->container);

        $registry = new ServiceProviderRegistry($this->container);
        $registry->register($providerClass);
    }

    /**
     * @throws NotFoundException
     *
     * @psalm-suppress ArgumentTypeCoercion
     * @psalm-suppress UndefinedClass
     */
    public function testRegisterThrowsWhenClassDoesNotExist(): void
    {
        $registry = new ServiceProviderRegistry($this->container);

        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage("Service provider class 'FakeProvider' does not exist.");

        $registry->register('FakeProvider');
    }

    /**
     * @throws NotFoundException
     */
    public function testRegisterThrowsWhenClassIsNotAProvider(): void
    {
        $registry = new ServiceProviderRegistry($this->container);

        $invalidClass = stdClass::class;

        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage("Service provider '$invalidClass' must implement ServiceProviderInterface.");

        /**  @psalm-suppress InvalidArgument intended */
        $registry->register($invalidClass);
    }

    /**
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function testBootCallsBootOnAllTaggedProviders(): void
    {
        $registry = new ServiceProviderRegistry($this->container);

        $provider1 = $this->createMock(ServiceProviderInterface::class);
        $provider2 = $this->createMock(ServiceProviderInterface::class);

        $this->container->expects($this->once())
            ->method('getTagged')
            ->with('service.provider')
            ->willReturn([$provider1, $provider2]);

        $provider1->expects($this->once())->method('boot')->with($this->container);
        $provider2->expects($this->once())->method('boot')->with($this->container);

        $registry->boot();
    }
}
