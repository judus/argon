<?php

declare(strict_types=1);

namespace Tests\Unit\Container;

use Maduser\Argon\Container\Contracts\TypeInterceptorInterface;
use Maduser\Argon\Container\Exceptions\CircularDependencyException;
use Maduser\Argon\Container\Exceptions\ContainerException;
use Maduser\Argon\Container\Exceptions\NotFoundException;
use Maduser\Argon\Container\ParameterOverrideRegistry;
use Maduser\Argon\Container\ServiceContainer;
use PHPUnit\Framework\TestCase;
use ReflectionException;
use ReflectionProperty;
use stdClass;
use Tests\Mocks\TestService;
use Tests\Mocks\TestServiceWithDependency;
use Tests\Mocks\TestServiceWithMultipleParams;
use Tests\Mocks\UninstantiableClass;

class ServiceContainerTest extends TestCase
{
    public function testSingletonServiceBinding()
    {
        $container = new ServiceContainer();
        $container->singleton('service', fn() => new stdClass());

        $instance1 = $container->get('service');
        $instance2 = $container->get('service');

        $this->assertSame($instance1, $instance2, 'Singleton services should return the same instance.');
    }

    public function testTransientServiceBinding()
    {
        $container = new ServiceContainer();
        $container->bind('service', fn() => new stdClass(), false);

        $instance1 = $container->get('service');
        $instance2 = $container->get('service');

        $this->assertNotSame($instance1, $instance2, 'Transient services should return different instances.');
    }

    public function testRegisterFactorySingleton()
    {
        $container = new ServiceContainer();
        $container->registerFactory('factory', fn() => new stdClass(), true);

        $instance1 = $container->get('factory');
        $instance2 = $container->get('factory');

        $this->assertSame($instance1, $instance2, 'Factory with singleton flag should return the same instance.');
    }

    public function testRegisterFactoryTransient()
    {
        $container = new ServiceContainer();
        $container->registerFactory('factory', fn() => new stdClass(), false);

        $instance1 = $container->get('factory');
        $instance2 = $container->get('factory');

        $this->assertNotSame($instance1, $instance2, 'Factory without singleton flag should return different instances.');
    }

    public function testCircularDependencyDetection()
    {
        $container = new ServiceContainer();

        // Bind services that cause a circular dependency
        $container->singleton('A', fn() => $container->get('B'));
        $container->singleton('B', fn() => $container->get('A'));

        // Expect the consolidated ContainerException instead of CircularDependencyException
        $this->expectException(ContainerException::class);

        // Assert the correct exception message
        $this->expectExceptionMessage("Circular dependency detected for service 'A'. Chain: A -> B -> A");

        // Trigger the circular dependency
        $container->get('A');
    }

    public function testParameterResolutionWithOverride()
    {
        $overrideRegistry = $this->createMock(ParameterOverrideRegistry::class);

        // Mock the override for the 'dependency' parameter in TestService
        $overrideRegistry->expects($this->once())
            ->method('getOverridesForClass')
            ->with(TestService::class)
            ->willReturn(['dependency' => 'overriddenValue']);

        $container = new ServiceContainer($overrideRegistry);

        // Bind the service and let the container resolve dependencies
        $container->bind(TestService::class, TestService::class);

        // Resolve the service and assert the override is applied
        $resolvedService = $container->get(TestService::class);

        // Assert that the overridden value was applied to the dependency
        $this->assertEquals('overriddenValue', $resolvedService->getDependency());
    }


    public function testTaggingAndRetrievingServices()
    {
        $container = new ServiceContainer();
        $container->singleton('service1', fn() => new stdClass());
        $container->singleton('service2', fn() => new stdClass());

        $container->tag('service1', ['groupA']);
        $container->tag('service2', ['groupA', 'groupB']);

        $groupA = $container->getTaggedServices('groupA');
        $groupB = $container->getTaggedServices('groupB');

        $this->assertCount(2, $groupA, 'GroupA should contain two services.');
        $this->assertCount(1, $groupB, 'GroupB should contain one service.');
    }

    public function testThrowsNotFoundException()
    {
        $this->expectException(NotFoundException::class);

        $container = new ServiceContainer();
        $container->get('nonExistentService');
    }

    public function testThrowsContainerExceptionForUninstantiableClass()
    {
        $this->expectException(ContainerException::class);

        $container = new ServiceContainer();
        $container->get(UninstantiableClass::class);  // Abstract class, can't be instantiated
    }

    public function testBindThrowsExceptionForInvalidClass()
    {
        $this->expectException(ContainerException::class);
        $container = new ServiceContainer();
        $container->bind('invalidService', 'NonExistentClass');
    }

    public function testMissingParameterOverrideThrowsException()
    {
        $overrideRegistry = $this->createMock(ParameterOverrideRegistry::class);
        $overrideRegistry->method('getOverridesForClass')->willReturn([]);

        $container = new ServiceContainer($overrideRegistry);

        $this->expectException(ContainerException::class);
        $container->bind(TestService::class, TestService::class);
        $container->get(TestService::class);
    }

    public function testTypeInterceptorModifiesResolvedInstance()
    {
        // Create a mock for the correct interface
        $interceptor = $this->createMock(TypeInterceptorInterface::class);

        // Set expectations for the mock behavior
        $interceptor->method('supports')->willReturn(true);
        $interceptor->expects($this->once())->method('intercept');

        // Create the container and register the interceptor
        $container = new ServiceContainer();
        $container->registerTypeInterceptor($interceptor);

        // Bind a service and resolve it, which should trigger the interceptor
        $container->bind('service', fn() => new stdClass());

        // This should trigger the interceptor's `intercept` method
        $container->get('service');
    }

    /**
     * @throws ReflectionException
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function testReflectionCacheUsed()
    {
        $container = new ServiceContainer();
        $reflectionProperty = new ReflectionProperty($container, 'reflectionCache');
        $reflectionProperty->setAccessible(true);

        // First time - cache should be empty
        $this->assertEmpty($reflectionProperty->getValue($container));

        // Resolve a class (stdClass doesn't have dependencies)
        $container->bind('service', stdClass::class);
        $container->get('service');

        // Now the reflection cache should contain one entry
        $this->assertCount(1, $reflectionProperty->getValue($container));
    }

    public function testMultipleParameterOverrides()
    {
        $overrideRegistry = $this->createMock(ParameterOverrideRegistry::class);
        $overrideRegistry->method('getOverridesForClass')
            ->willReturn(['param1' => 'override1', 'param2' => 'override2']);

        $container = new ServiceContainer($overrideRegistry);

        // Make sure the bind references the correct class
        $container->bind(TestServiceWithMultipleParams::class, TestServiceWithMultipleParams::class);

        $resolvedService = $container->get(TestServiceWithMultipleParams::class);

        $this->assertEquals('override1', $resolvedService->getParam1());
        $this->assertEquals('override2', $resolvedService->getParam2());
    }


    public function testCallResolvesMethodDependencies()
    {
        $container = new ServiceContainer();

        // Provide a default value for the constructor dependency
        $container->bind(TestService::class, fn() => new TestService('constructorDependency'));

        // Resolve the service and call the method
        $service = $container->get(TestService::class);
        $result = $container->call($service, 'someMethod');

        // Assert that the method returns the default value ('defaultValue')
        $this->assertEquals('defaultValue', $result);
    }


    public function testCallResolvesMethodWithOverride()
    {
        $container = new ServiceContainer();

        // Bind the TestService with its constructor dependency
        $container->bind(TestService::class, fn() => new TestService('constructorDependency'));

        // Resolve the service from the container
        $service = $container->get(TestService::class);

        // Call the method with an override for 'dependency'
        $result = $container->call($service, 'someMethod', ['dependency' => 'overrideValue']);

        // Assert that the method returns the overridden value
        $this->assertEquals('overrideValue', $result);
    }

    public function testThrowsContainerExceptionForNonInstantiableClass()
    {
        $container = new ServiceContainer();

        // Expect a ContainerException for non-instantiable classes like UninstantiableClass
        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage("Class 'Tests\Mocks\UninstantiableClass' (requested as 'Tests\Mocks\UninstantiableClass') is not instantiable.");

        // Trigger the exception by trying to resolve the abstract UninstantiableClass
        $container->get(\Tests\Mocks\UninstantiableClass::class);
    }


    public function testThrowsContainerExceptionForCircularDependency()
    {
        $container = new ServiceContainer();

        // Bind two services that cause a circular dependency
        $container->singleton('A', fn() => $container->get('B'));
        $container->singleton('B', fn() => $container->get('A'));

        // Expect the consolidated ContainerException
        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage("Circular dependency detected for service 'A'.");

        $container->get('A');

        // Optionally, check dependency chain
        try {
            $container->get('A');
        } catch (ContainerException $e) {
            $this->assertEquals('A', $e->getServiceId());
            $this->assertStringContainsString('A -> B -> A', $e->getMessage());
        }
    }

    public function testThrowsContainerExceptionForUnresolvedPrimitive()
    {
        $container = new ServiceContainer();

        // Bind a service with a primitive dependency
        $container->bind(\Tests\Mocks\TestService::class, \Tests\Mocks\TestService::class);

        // Expect a ContainerException with the fully qualified class name
        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage("Cannot resolve primitive type parameter 'dependency' in service 'Tests\Mocks\TestService'.");

        // Trigger the exception by calling the method that has a primitive parameter
        $container->get(\Tests\Mocks\TestService::class);
    }

    public function testThrowsNotFoundExceptionForNonExistentDependency()
    {
        $container = new ServiceContainer();

        // Expect a NotFoundException for the missing dependency class
        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage("Service 'Tests\\Mocks\\NonExistentDependency' not found.");

        // Bind the service with a dependency on a non-existent class
        $container->bind(\Tests\Mocks\TestServiceWithDependency::class, \Tests\Mocks\TestServiceWithDependency::class);

        // Trigger the exception by attempting to resolve the service
        $container->get(\Tests\Mocks\TestServiceWithDependency::class);
    }


}
