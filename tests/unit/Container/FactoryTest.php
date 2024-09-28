<?php

declare(strict_types=1);

namespace Tests\Unit\Container;

use Exception;
use Maduser\Argon\Container\Factory;
use Maduser\Argon\Container\ServiceContainer;
use PHPUnit\Framework\TestCase;
use ReflectionException;
use Tests\Mocks\ClassWithContainerDependency;
use Tests\Mocks\ClassWithDefaultValues;
use Tests\Mocks\ClassWithDependency;
use Tests\Mocks\ClassWithInterfaceDependency;
use Tests\Mocks\ClassWithOptionalParameters;
use Tests\Mocks\DependencyClass;
use Tests\Mocks\ExampleInterface;
use Tests\Mocks\ImplementationClass;
use Tests\Mocks\SimpleClass;

class FactoryTest extends TestCase
{
    private ServiceContainer $container;
    private Factory $factory;

    protected function setUp(): void
    {
        $this->container = new ServiceContainer();
        $this->factory = new Factory($this->container);
    }

    /**
     * @throws ReflectionException
     */
    public function testInstantiateClassWithoutDependencies(): void
    {
        $instance = $this->factory->make(SimpleClass::class);
        $this->assertInstanceOf(SimpleClass::class, $instance);
    }

    /**
     * @throws ReflectionException
     */
    public function testInstantiateClassWithDependencies(): void
    {
        $this->container->singleton(DependencyClass::class);
        $instance = $this->factory->make(ClassWithDependency::class);
        $this->assertInstanceOf(ClassWithDependency::class, $instance);
        $this->assertInstanceOf(DependencyClass::class, $instance->dependency);
    }

    /**
     * @throws ReflectionException
     */
    public function testInstantiationWithOptionalParameters(): void
    {
        $instance = $this->factory->make(ClassWithOptionalParameters::class);
        $this->assertInstanceOf(ClassWithOptionalParameters::class, $instance);
        $this->assertEquals('default', $instance->param);
    }

    /**
     * @throws ReflectionException
     */
    public function testHandlingOfInterfaceBindings(): void
    {
        $this->container->bind(ExampleInterface::class, ImplementationClass::class);
        $instance = $this->factory->make(ClassWithInterfaceDependency::class);
        $this->assertInstanceOf(ClassWithInterfaceDependency::class, $instance);
        $this->assertInstanceOf(ImplementationClass::class, $instance->interfaceDependency);
    }

    /**
     * @throws ReflectionException
     */
    public function testInjectionOfServiceContainer(): void
    {
        /** @var ClassWithContainerDependency $instance */
        $instance = $this->factory->make(ClassWithContainerDependency::class);
        $this->assertInstanceOf(ClassWithContainerDependency::class, $instance);
        $this->assertInstanceOf(ServiceContainer::class, $instance->container);
    }

    /**
     * @throws ReflectionException
     */
    public function testErrorHandlingForNonReflectableClass(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Failed to instantiate class 'NonExistentClass'");
        $this->factory->make('NonExistentClass');
    }

    /**
     * @throws ReflectionException
     */
    public function testHandlingOfDefaultParameterValues(): void
    {
        $instance = $this->factory->make(ClassWithDefaultValues::class, ['param1' => 'custom']);
        $this->assertEquals('custom', $instance->param1);
        $this->assertEquals(42, $instance->param2); // Default value should be 42
    }
}
