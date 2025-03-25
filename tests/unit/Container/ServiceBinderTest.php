<?php

declare(strict_types=1);

namespace Tests\Unit\Container;

use Closure;
use Maduser\Argon\Container\Exceptions\ContainerException;
use Maduser\Argon\Container\ServiceBinder;
use Maduser\Argon\Container\ServiceDescriptor;
use PHPUnit\Framework\TestCase;
use stdClass;

class ServiceBinderTest extends TestCase
{
    /**
     * @throws ContainerException
     */
    public function testSingletonRegistersItselfAsConcrete(): void
    {
        $binder = new ServiceBinder();
        $binder->singleton(stdClass::class);

        $descriptor = $binder->getDescriptor(stdClass::class);

        $this->assertInstanceOf(ServiceDescriptor::class, $descriptor);
        $this->assertSame(stdClass::class, $descriptor->getConcrete());
        $this->assertTrue($descriptor->isSingleton());
    }

    /**
     * @throws ContainerException
     */
    public function testSingletonRegistersClosure(): void
    {
        $binder = new ServiceBinder();

        $closure = fn(): object => new stdClass();
        $binder->singleton('my-service', $closure);

        $descriptor = $binder->getDescriptor('my-service');

        $this->assertInstanceOf(ServiceDescriptor::class, $descriptor);
        $this->assertSame($closure, $descriptor->getConcrete());
        $this->assertTrue($descriptor->isSingleton());
    }

    /**
     * @throws ContainerException
     */
    public function testBindRegistersNonSingletonByDefault(): void
    {
        $binder = new ServiceBinder();
        $binder->bind(stdClass::class);

        $descriptor = $binder->getDescriptor(stdClass::class);

        $this->assertInstanceOf(ServiceDescriptor::class, $descriptor);
        $this->assertFalse($descriptor->isSingleton());
    }

    public function testBindThrowsForInvalidConcreteString(): void
    {
        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage("Class 'TotallyFakeClass' does not exist.");

        $binder = new ServiceBinder();
        $binder->bind('fake-service', 'TotallyFakeClass');
    }

    /**
     * @throws ContainerException
     */
    public function testHasReturnsTrueForRegisteredService(): void
    {
        $binder = new ServiceBinder();
        $binder->singleton('thing', fn() => new stdClass());

        $this->assertTrue($binder->has('thing'));
    }

    public function testHasReturnsFalseForUnregisteredService(): void
    {
        $binder = new ServiceBinder();

        $this->assertFalse($binder->has('ghost'));
    }

    public function testGetDescriptorReturnsNullIfNotSet(): void
    {
        $binder = new ServiceBinder();

        $this->assertNull($binder->getDescriptor('nonexistent'));
    }

    /**
     * @throws ContainerException
     */
    public function testGetDescriptorsReturnsAll(): void
    {
        $binder = new ServiceBinder();
        $binder->bind(stdClass::class);
        $binder->singleton('singleton', fn() => new stdClass());

        $all = $binder->getDescriptors();

        $this->assertCount(2, $all);
        $this->assertArrayHasKey(stdClass::class, $all);
        $this->assertArrayHasKey('singleton', $all);
    }

    public function testRegisterFactoryWrapsFactoryInClosure(): void
    {
        $binder = new ServiceBinder();

        $factory = fn(): object => new stdClass();
        $binder->registerFactory('factory-service', $factory);

        $descriptor = $binder->getDescriptor('factory-service');
        $this->assertInstanceOf(ServiceDescriptor::class, $descriptor);
        $this->assertTrue($descriptor->isSingleton());

        $wrapped = $descriptor->getConcrete();
        $this->assertInstanceOf(Closure::class, $wrapped);

        $result = $wrapped();
        $this->assertInstanceOf(stdClass::class, $result);
    }

    public function testRegisterFactoryWithNonSingleton(): void
    {
        $binder = new ServiceBinder();

        $factory = fn(): object => new stdClass();
        $binder->registerFactory('non-single-factory', $factory, false);

        $descriptor = $binder->getDescriptor('non-single-factory');
        $this->assertFalse($descriptor->isSingleton());
    }
}
