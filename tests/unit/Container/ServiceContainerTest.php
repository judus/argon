<?php

declare(strict_types=1);

namespace Tests\Unit\Container;

use Maduser\Argon\Container\Contracts\ParameterRegistryInterface;
use Maduser\Argon\Container\Contracts\InterceptorInterface;
use Maduser\Argon\Container\Contracts\PostResolutionInterceptorInterface;
use Maduser\Argon\Container\Exceptions\ContainerException;
use Maduser\Argon\Container\Exceptions\NotFoundException;
use Maduser\Argon\Container\ParameterRegistry;
use Maduser\Argon\Container\ServiceContainer;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionException;
use ReflectionProperty;
use stdClass;
use Tests\Mocks\ClassWithEmptyConstructor;
use Tests\Mocks\TestConcreteClass;
use Tests\Mocks\TestDependency;
use Tests\Mocks\TestInterface;
use Tests\Mocks\TestService;
use Tests\Mocks\TestServiceWithDependency;
use Tests\Mocks\TestServiceWithMultipleParams;
use Tests\Mocks\TestServiceWithNonExistentDependency;
use Tests\Mocks\UninstantiableClass;
use Tests\Unit\Container\Mocks\UserService;

class ServiceContainerTest extends TestCase
{
    /**
     * @throws NotFoundException
     * @throws ContainerException
     */
    public function testSingletonServiceBinding(): void
    {
        $container = new ServiceContainer();
        $container->singleton('service', fn() => new stdClass());

        $instance1 = $container->get('service');
        $instance2 = $container->get('service');

        $this->assertSame($instance1, $instance2, 'Singleton services should return the same instance.');
    }

    /**
     * @throws NotFoundException
     * @throws ContainerException
     */
    public function testTransientServiceBinding(): void
    {
        $container = new ServiceContainer();
        $container->bind('service', fn() => new stdClass());

        $instance1 = $container->get('service');
        $instance2 = $container->get('service');

        $this->assertNotSame($instance1, $instance2, 'Transient services should return different instances.');
    }

    /**
     * @throws NotFoundException
     * @throws ContainerException
     */
    public function testRegisterFactorySingleton(): void
    {
        $container = new ServiceContainer();
        $container->registerFactory('factory', fn() => new stdClass());

        $instance1 = $container->get('factory');
        $instance2 = $container->get('factory');

        $this->assertSame(
            $instance1,
            $instance2,
            'Factory with singleton flag should return the same instance.'
        );
    }

    /**
     * @throws NotFoundException
     * @throws ContainerException
     */
    public function testRegisterFactoryTransient(): void
    {
        $container = new ServiceContainer();
        $container->registerFactory('factory', fn() => new stdClass(), false);

        $instance1 = $container->get('factory');
        $instance2 = $container->get('factory');

        $this->assertNotSame(
            $instance1,
            $instance2,
            'Factory without singleton flag should return different instances.'
        );
    }

    /**
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function testCircularDependencyDetection(): void
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

    /**
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function testParameterResolutionWithOverride(): void
    {
        /** @var MockObject&ParameterRegistryInterface $parameters */
        $parameters = $this->createMock(ParameterRegistryInterface::class);

        // Mock the override for the 'dependency' parameter in TestService
        $parameters->expects($this->once())
            ->method('get')
            ->with(TestService::class)
            ->willReturn(['dependency' => 'overriddenValue']);

        // Create the container with the mocked override registry
        $container = new ServiceContainer($parameters);

        // No need to bind TestService to itself. Just let autowiring resolve it.
        // Resolve the service and assert the override is applied
        $resolvedService = $container->get(TestService::class);

        // Assert that the overridden value was applied to the dependency
        $this->assertEquals('overriddenValue', $resolvedService->getDependency());
    }

    /**
     * @throws NotFoundException
     * @throws ContainerException
     */
    public function testTaggingAndRetrievingServices(): void
    {
        $container = new ServiceContainer();
        $container->singleton('service1', fn() => new stdClass());
        $container->singleton('service2', fn() => new stdClass());

        $container->tag('service1', ['groupA']);
        $container->tag('service2', ['groupA', 'groupB']);

        $groupA = $container->getTagged('groupA');
        $groupB = $container->getTagged('groupB');

        $this->assertCount(2, $groupA, 'GroupA should contain two services.');
        $this->assertCount(1, $groupB, 'GroupB should contain one service.');
    }

    /**
     * @throws ContainerException
     */
    public function testThrowsNotFoundException(): void
    {
        $this->expectException(NotFoundException::class);

        $container = new ServiceContainer();
        $container->get('nonExistentService');
    }

    /**
     * @throws NotFoundException
     */
    public function testThrowsContainerExceptionForUninstantiableClass(): void
    {
        $this->expectException(ContainerException::class);

        $container = new ServiceContainer();
        $container->get(UninstantiableClass::class);  // Abstract class, can't be instantiated
    }

    public function testBindThrowsExceptionForInvalidClass(): void
    {
        $this->expectException(ContainerException::class);
        $container = new ServiceContainer();
        $container->bind('invalidService', 'NonExistentClass');
    }

    /**
     * @throws NotFoundException
     */
    public function testMissingParameterOverrideThrowsException(): void
    {
        $parameters = $this->createMock(ParameterRegistryInterface::class);
        $parameters->method('get')->willReturn([]);

        $container = new ServiceContainer($parameters);

        $this->expectException(ContainerException::class);
        $container->get(TestService::class);
    }

    /**
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function testTypeInterceptorModifiesResolvedInstance(): void
    {
        // Define a concrete interceptor class inline for clarity/testing
        $interceptor = new class implements PostResolutionInterceptorInterface {
            public static function supports(object|string $target): bool
            {
                return $target === stdClass::class || $target instanceof \stdClass;
            }

            public function intercept(object $instance): void
            {
                $instance->intercepted = true;
            }
        };

        // Register interceptor as FQCN (as expected now)
        $container = new ServiceContainer();
        $container->registerInterceptor(get_class($interceptor));

        // Bind a service (autowiring would also work)
        $container->bind('service', fn() => new \stdClass());

        // Resolve the service
        $instance = $container->get('service');

        // Assertion
        $this->assertTrue($instance->intercepted ?? false, 'Service instance should be intercepted.');
    }

    /**
     * @throws ReflectionException
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function testReflectionCacheUsed(): void
    {
        $container = new ServiceContainer();

        // Get the ReflectionCache instance from the container
        $reflectionCacheProperty = new ReflectionProperty($container, 'reflectionCache');
        $reflectionCacheProperty->setAccessible(true);
        $reflectionCache = $reflectionCacheProperty->getValue($container);

        // Now reflect into the ReflectionCache's internal cache
        $internalCacheProperty = new ReflectionProperty($reflectionCache, 'reflectionCache');
        $internalCacheProperty->setAccessible(true);

        // First time - cache should be empty
        $this->assertEmpty($internalCacheProperty->getValue($reflectionCache));

        // Resolve a class (stdClass doesn't have dependencies)
        $container->bind('service', \stdClass::class);
        $container->get('service');

        // Now the reflection cache should contain one entry
        $this->assertCount(1, $internalCacheProperty->getValue($reflectionCache));
    }

    /**
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function testMultipleParameterOverrides(): void
    {
        $parameters = $this->createMock(ParameterRegistryInterface::class);
        $parameters->method('get')
            ->willReturn(['param1' => 'override1', 'param2' => 'override2']);

        $container = new ServiceContainer($parameters);

        $resolvedService = $container->get(TestServiceWithMultipleParams::class);

        $this->assertEquals('override1', $resolvedService->getParam1());
        $this->assertEquals('override2', $resolvedService->getParam2());
    }

    /**
     * @throws NotFoundException
     * @throws ContainerException
     */
    public function testCallResolvesMethodDependencies(): void
    {
        $container = new ServiceContainer();

        // Provide a default value for the constructor dependency
        $container->bind(TestService::class, fn() => new TestService('constructorDependency'));

        // Resolve the service and call the method
        $service = $container->get(TestService::class);
        $result = $container->invoke($service, 'someMethod');

        // Assert that the method returns the default value ('defaultValue')
        $this->assertEquals('defaultValue', $result);
    }

    /**
     * @throws NotFoundException
     * @throws ContainerException
     */
    public function testCallResolvesMethodWithOverride(): void
    {
        $container = new ServiceContainer();

        // Bind the TestService with its constructor dependency
        $container->bind(TestService::class, fn() => new TestService('constructorDependency'));

        // Resolve the service from the container
        $service = $container->get(TestService::class);

        // Call the method with an override for 'dependency'
        $result = $container->invoke($service, 'someMethod', ['dependency' => 'overrideValue']);

        // Assert that the method returns the overridden value
        $this->assertEquals('overrideValue', $result);
    }

    /**
     * @throws NotFoundException
     */
    public function testThrowsContainerExceptionForNonInstantiableClass(): void
    {
        $container = new ServiceContainer();

        // Expect a ContainerException for non-instantiable classes like UninstantiableClass
        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage(
            "Class 'Tests\Mocks\UninstantiableClass' " .
            "(requested as 'Tests\Mocks\UninstantiableClass') is not instantiable."
        );

        // Trigger the exception by trying to resolve the abstract UninstantiableClass
        $container->get(UninstantiableClass::class);
    }

    /**
     * @throws NotFoundException
     * @throws ContainerException
     */
    public function testThrowsContainerExceptionForCircularDependency(): void
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

    /**
     * @throws NotFoundException
     */
    public function testThrowsContainerExceptionForUnresolvedPrimitive(): void
    {
        $container = new ServiceContainer();

        // Expect a ContainerException with the fully qualified class name
        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage(
            "Cannot resolve primitive parameter 'dependency' in service 'Tests\Mocks\TestService'."
        );

        // Trigger the exception by calling the method that has a primitive parameter
        $container->get(TestService::class);
    }

    /**
     * @throws ContainerException
     */
    public function testThrowsNotFoundExceptionForNonExistentDependency(): void
    {
        $container = new ServiceContainer();

        // Expect a NotFoundException for the missing dependency class
        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage("Service 'Tests\\Mocks\\NonExistentDependency' not found.");

        // Trigger the exception by attempting to resolve the service
        $container->get(TestServiceWithNonExistentDependency::class);
    }

    /**
     * @throws NotFoundException
     * @throws ContainerException
     */
    public function testSingletonReturnsSameInstance(): void
    {
        $container = new ServiceContainer();

        // Bind a singleton service
        $container->singleton('service', fn() => new stdClass());

        // Fetch the service twice
        $instance1 = $container->get('service');
        $instance2 = $container->get('service');

        // Assert that both instances are the same (singleton)
        $this->assertSame($instance1, $instance2, 'Singleton service should return the same instance.');
    }

    /**
     * @throws NotFoundException
     * @throws ContainerException
     */
    public function testTransientReturnsDifferentInstances(): void
    {
        $container = new ServiceContainer();

        // Bind a non-singleton service
        $container->bind('service', fn() => new stdClass());

        // Fetch the service twice
        $instance1 = $container->get('service');
        $instance2 = $container->get('service');

        // Assert that the instances are different (transient)
        $this->assertNotSame($instance1, $instance2, 'Transient service should return different instances.');
    }

    /**
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function testServiceReceivesDependencyThroughAutowiring(): void
    {
        $container = new ServiceContainer();

        // Fetch the service
        $service = $container->get(TestServiceWithDependency::class);

        // Assert the dependency was injected
        $this->assertInstanceOf(TestDependency::class, $service->getDependency());
    }

    /**
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function testServiceReceivesDependencyWithoutAutowiring(): void
    {
        $container = new ServiceContainer();

        // Manually bind the service with its dependency
        $container->bind(
            TestServiceWithDependency::class,
            fn() => new TestServiceWithDependency(new TestDependency())
        );

        // Fetch the service
        $service = $container->get(TestServiceWithDependency::class);

        // Assert the dependency was injected
        $this->assertInstanceOf(TestDependency::class, $service->getDependency());
    }

    /**
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function testInterfaceToClassResolution(): void
    {
        $container = new ServiceContainer();

        // Bind the interface to a concrete class
        $container->bind(TestInterface::class, TestConcreteClass::class);

        // Fetch the interface, which should resolve to the concrete class
        $instance = $container->get(TestInterface::class);

        // Assert that the resolved instance is of the concrete class
        $this->assertInstanceOf(TestConcreteClass::class, $instance);
    }

    /**
     * @throws ContainerException
     */
    public function testInterfaceResolutionThrowsExceptionIfNotBound(): void
    {
        $container = new ServiceContainer();

        // Try to fetch an interface without binding
        $this->expectException(NotFoundException::class);
        $container->get(TestInterface::class);
    }

    /**
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function testConcreteClassIsResolved(): void
    {
        $container = new ServiceContainer();

        // No manual binding required, it should autowire
        $instance = $container->get(TestConcreteClass::class);

        // Assert the instance is of the correct class
        $this->assertInstanceOf(TestConcreteClass::class, $instance);
    }

    /**
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function testBindingClassToItselfWorksFabulously(): void
    {
        $container = new ServiceContainer();

        $container->bind(TestConcreteClass::class, TestConcreteClass::class);

        $instance = $container->get(TestConcreteClass::class);

        $this->assertInstanceOf(TestConcreteClass::class, $instance);
    }

    /**
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function testAutowiringWithMultipleDependencies(): void
    {
        $parameters = $this->createMock(ParameterRegistryInterface::class);
        $parameters->method('get')
            ->willReturn([
                'param1' => 'stringValue', // Override for string param
                'param2' => 123            // Override for int param
            ]);

        $container = new ServiceContainer($parameters);

        // Test the service resolution with overrides
        $service = $container->get(TestServiceWithMultipleParams::class);

        $this->assertEquals('stringValue', $service->getParam1());
        $this->assertEquals(123, $service->getParam2());
    }

    /**
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function testRecursiveDependencyResolution(): void
    {
        $container = new ServiceContainer();

        // Assuming TestServiceWithDependency depends on TestDependency
        $container->bind(
            TestServiceWithDependency::class,
            fn() => new TestServiceWithDependency(new TestDependency())
        );

        // Fetching the outermost service should recursively resolve all dependencies
        $service = $container->get(TestServiceWithDependency::class);

        $this->assertInstanceOf(TestDependency::class, $service->getDependency());
    }

    /**
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function testPrimitiveParameterResolutionWithOverrides(): void
    {
        $parameters = $this->createMock(ParameterRegistryInterface::class);
        $parameters->method('get')
            ->willReturn(['param1' => 'override1', 'param2' => 123]);

        $container = new ServiceContainer($parameters);

        $service = $container->get(TestServiceWithMultipleParams::class);

        $this->assertEquals('override1', $service->getParam1());
        $this->assertEquals(123, $service->getParam2());
    }

    /**
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function testLazyLoadingOfServices(): void
    {
        $container = new ServiceContainer();

        // Use a flag to track instantiation
        $isInstantiated = false;

        $container->singleton('lazyService', function () use (&$isInstantiated) {
            $isInstantiated = true;

            return new TestService('some-dependency');
        });

        // At this point, the service should NOT be instantiated
        $this->assertFalse($isInstantiated, 'Service should not be instantiated yet.');

        // Now, actually resolve the service, triggering the instantiation
        $service = $container->get('lazyService');

        // After calling get(), the service should be instantiated
        $this->assertTrue($isInstantiated, 'Service should be instantiated after calling get().');
        $this->assertInstanceOf(TestService::class, $service);
    }

    /**
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function testAutowiringWithEmptyConstructor(): void
    {
        $container = new ServiceContainer();
        $instance = $container->get(ClassWithEmptyConstructor::class);

        $this->assertInstanceOf(ClassWithEmptyConstructor::class, $instance);
    }

    /**
     * @throws NotFoundException
     * @throws ContainerException
     */
    public function testMultipleServicesResolution(): void
    {
        $container = new ServiceContainer();

        for ($i = 0; $i < 1000; $i++) {
            $container->singleton("service$i", fn() => new stdClass());
        }

        for ($i = 0; $i < 1000; $i++) {
            $service = $container->get("service$i");
            $this->assertInstanceOf(stdClass::class, $service);
        }
    }

    public function testCanResolveReturnsTrueForExistingClass(): void
    {
        $container = new ServiceContainer();
        $this->assertTrue($container->isResolvable(\stdClass::class));
        $this->assertFalse($container->isResolvable('NonExistentClass'));
    }

    /**
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function testExtendOverridesResolvedService(): void
    {
        $container = new ServiceContainer();

        // Original service
        $container->singleton(stdClass::class, fn() => new stdClass());

        // Trigger the initial resolution
        $original = $container->get(stdClass::class);

        // Now extend it with a decorator
        $decoratedInstance = new class ($original) extends stdClass {
            public function __construct(public stdClass $wrapped)
            {
            }
        };

        /** @psalm-suppress ArgumentTypeCoercion */
        $container->extend(stdClass::class, fn(stdClass $original): object => $decoratedInstance);

        // Re-resolve the service
        $resolved = $container->get(stdClass::class);

        $this->assertInstanceOf(stdClass::class, $resolved);
        $this->assertSame($decoratedInstance, $resolved);
        $this->assertSame($original, $resolved->wrapped);
    }

    public function testResolvesNestedDependencies(): void
    {
        $container = new ServiceContainer();

        // No bindings needed, just pure autowiring magic.
        $instance = $container->get(UserService::class);

        $this->assertInstanceOf(UserService::class, $instance);
    }
}
