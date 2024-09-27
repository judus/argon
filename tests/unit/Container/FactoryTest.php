<?php

declare(strict_types=1);

namespace Maduser\Argon\Tests\Unit\Container;

use Exception;
use Maduser\Argon\Container\Factory;
use Maduser\Argon\Container\ServiceContainer;
use PHPUnit\Framework\TestCase;
use ReflectionException;

class FactoryTest extends TestCase
{
    private ServiceContainer $container;
    private Factory $factory;

    protected function setUp(): void
    {
        $this->container = new ServiceContainer();
        $this->factory = new Factory($this->container);
    }

    // Test 1: Test instantiation of a class without dependencies
    public function testInstantiateClassWithoutDependencies(): void
    {
        $instance = $this->factory->make(SimpleClass::class);
        $this->assertInstanceOf(SimpleClass::class, $instance);
    }

    // Test 2: Test instantiation of a class with dependencies
    public function testInstantiateClassWithDependencies(): void
    {
        $this->container->singleton(DependencyClass::class);
        $instance = $this->factory->make(ClassWithDependency::class);
        $this->assertInstanceOf(ClassWithDependency::class, $instance);
        $this->assertInstanceOf(DependencyClass::class, $instance->dependency);
    }

    // Test 3: Test instantiation with optional parameters
    public function testInstantiationWithOptionalParameters(): void
    {
        $instance = $this->factory->make(ClassWithOptionalParameters::class);
        $this->assertInstanceOf(ClassWithOptionalParameters::class, $instance);
        $this->assertEquals('default', $instance->param);
    }

    // Test 4: Test handling of interface bindings
    public function testHandlingOfInterfaceBindings(): void
    {
        $this->container->bind(ExampleInterface::class, ImplementationClass::class);
        $instance = $this->factory->make(ClassWithInterfaceDependency::class);
        $this->assertInstanceOf(ClassWithInterfaceDependency::class, $instance);
        $this->assertInstanceOf(ImplementationClass::class, $instance->interfaceDependency);
    }

    // Test 5: Test injection of the ServiceContainer
    public function testInjectionOfServiceContainer(): void
    {
        $instance = $this->factory->make(ClassWithContainerDependency::class);
        $this->assertInstanceOf(ClassWithContainerDependency::class, $instance);
        $this->assertInstanceOf(ServiceContainer::class, $instance->container);
    }

    // Test 6: Test error handling for non-reflectable classes
    public function testErrorHandlingForNonReflectableClass(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Failed to instantiate class 'NonExistentClass'");
        $this->factory->make('NonExistentClass');
    }

    // Test 7: Test handling of default parameter values
    public function testHandlingOfDefaultParameterValues(): void
    {
        $instance = $this->factory->make(ClassWithDefaultValues::class, ['param1' => 'custom']);
        $this->assertEquals('custom', $instance->param1);
        $this->assertEquals(42, $instance->param2); // Default value should be 42
    }
}

// Mock classes for testing

class SimpleClass
{
}

class DependencyClass
{
}

class ClassWithDependency
{
    public DependencyClass $dependency;

    public function __construct(DependencyClass $dependency)
    {
        $this->dependency = $dependency;
    }
}

class ClassWithOptionalParameters
{
    public string $param;

    public function __construct(string $param = 'default')
    {
        $this->param = $param;
    }
}

interface ExampleInterface
{
}

class ImplementationClass implements ExampleInterface
{
}

class ClassWithInterfaceDependency
{
    public ExampleInterface $interfaceDependency;

    public function __construct(ExampleInterface $interfaceDependency)
    {
        $this->interfaceDependency = $interfaceDependency;
    }
}

class ClassWithContainerDependency
{
    public ServiceContainer $container;

    public function __construct(ServiceContainer $container)
    {
        $this->container = $container;
    }
}

class ClassWithDefaultValues
{
    public string $param1;
    public int $param2;

    public function __construct(string $param1, int $param2 = 42)
    {
        $this->param1 = $param1;
        $this->param2 = $param2;
    }
}
