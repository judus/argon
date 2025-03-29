<?php

declare(strict_types=1);

namespace Tests\Unit\Container;

use Maduser\Argon\Container\Contracts\ArgumentMapInterface;
use Maduser\Argon\Container\Contracts\InterceptorInterface;
use Maduser\Argon\Container\Contracts\InterceptorRegistryInterface;
use Maduser\Argon\Container\Contracts\PostResolutionInterceptorInterface;
use Maduser\Argon\Container\Contracts\PreResolutionInterceptorInterface;
use Maduser\Argon\Container\Contracts\ServiceBinderInterface;
use Maduser\Argon\Container\Contracts\ServiceDescriptorInterface;
use Maduser\Argon\Container\Exceptions\ContainerException;
use Maduser\Argon\Container\Exceptions\NotFoundException;
use Maduser\Argon\Container\ArgumentMap;
use Maduser\Argon\Container\ArgonContainer;
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
        $container = new ArgonContainer();
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
        $container = new ArgonContainer();
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
        $container = new ArgonContainer();
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
        $container = new ArgonContainer();
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
        $container = new ArgonContainer();

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
        /** @var MockObject&ArgumentMapInterface $parameters */
        $arguments = $this->createMock(ArgumentMapInterface::class);

        // Mock the override for the 'dependency' parameter in TestService
        $arguments->expects($this->once())
            ->method('get')
            ->with(TestService::class)
            ->willReturn(['dependency' => 'overriddenValue']);

        // Create the container with the mocked override registry
        $container = new ArgonContainer($arguments);

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
        $container = new ArgonContainer();
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

        $container = new ArgonContainer();
        $container->get('nonExistentService');
    }

    /**
     * @throws NotFoundException
     */
    public function testThrowsContainerExceptionForUninstantiableClass(): void
    {
        $this->expectException(ContainerException::class);

        $container = new ArgonContainer();
        $container->get(UninstantiableClass::class);  // Abstract class, can't be instantiated
    }

    public function testBindThrowsExceptionForInvalidClass(): void
    {
        $this->expectException(ContainerException::class);
        $container = new ArgonContainer();
        $container->bind('invalidService', 'NonExistentClass');
    }

    /**
     * @throws NotFoundException
     */
    public function testMissingParameterOverrideThrowsException(): void
    {
        $parameters = $this->createMock(ArgumentMapInterface::class);
        $parameters->method('getArgument')->willReturn([]);

        $container = new ArgonContainer($parameters);

        $this->expectException(ContainerException::class);
        $container->get(TestService::class);
    }

    public function testRegisterInterceptorThrowsIfClassDoesNotExist(): void
    {
        $container = new ArgonContainer();

        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage("Interceptor class 'NotARealInterceptor' does not exist.");

        /** @noinspection PhpUndefinedClass */
        /** @psalm-suppress UndefinedClass */
        /** @psalm-suppress ArgumentTypeCoercion */
        $container->registerInterceptor('NotARealInterceptor');
    }

   /**
     * @throws ContainerException
     */
    public function testRegisterInterceptorRegistersPreInterceptor(): void
    {
        $container = new ArgonContainer();

        $class = new class implements PreResolutionInterceptorInterface {
            public static function supports(object|string $target): bool
            {
                return true;
            }

            public function intercept(string $id, array &$parameters = []): ?object
            {
                return null;
            }
        };

        // Fully qualify to get the FQCN
        $fqcn = get_class($class);

        // Should not throw
        $container->registerInterceptor($fqcn);

        $this->assertTrue(true); // If it didn’t crash, it worked
    }

    /**
     * @throws ContainerException
     */
    public function testRegisterInterceptorRegistersPostInterceptor(): void
    {
        $container = new ArgonContainer();

        $interceptor = new class implements PostResolutionInterceptorInterface {
            public static function supports(object|string $target): bool
            {
                return true;
            }

            public function intercept(object $instance): void
            {
                // noop
            }
        };

        $fqcn = get_class($interceptor);

        // Should not throw
        $container->registerInterceptor($fqcn);

        $this->assertTrue(true); // If we got here, registration succeeded
    }

    public function testGetPostInterceptorsReturnsCorrectList(): void
    {
        $interceptors = $this->createMock(InterceptorRegistryInterface::class);
        $interceptors->expects($this->once())
            ->method('allPost')
            ->willReturn(['PostInterceptor']);

        $container = new ArgonContainer(interceptors: $interceptors);

        $this->assertSame(['PostInterceptor'], $container->getPostInterceptors());
    }

    public function testGetPreInterceptorsReturnsCorrectList(): void
    {
        $interceptors = $this->createMock(InterceptorRegistryInterface::class);
        $interceptors->expects($this->once())
            ->method('allPre')
            ->willReturn(['PreInterceptor']);

        $container = new ArgonContainer(interceptors: $interceptors);

        $this->assertSame(['PreInterceptor'], $container->getPreInterceptors());
    }

    /**
     * @throws ReflectionException
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function testReflectionCacheUsed(): void
    {
        $container = new ArgonContainer();

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
        $container->bind('service', stdClass::class);
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
        $arguments = $this->createMock(ArgumentMapInterface::class);
        $arguments->method('get')
            ->willReturn(['param1' => 'override1', 'param2' => 'override2']);

        $container = new ArgonContainer($arguments);

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
        $container = new ArgonContainer();

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
        $container = new ArgonContainer();

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
        $container = new ArgonContainer();

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
        $container = new ArgonContainer();

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
            $this->assertStringContainsString('A -> B -> A', $e->getMessage());
        }
    }

    /**
     * @throws NotFoundException
     */
    public function testThrowsContainerExceptionForUnresolvedPrimitive(): void
    {
        $container = new ArgonContainer();

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
        $container = new ArgonContainer();

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
        $container = new ArgonContainer();

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
        $container = new ArgonContainer();

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
        $container = new ArgonContainer();

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
        $container = new ArgonContainer();

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
        $container = new ArgonContainer();

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
        $container = new ArgonContainer();

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
        $container = new ArgonContainer();

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
        $container = new ArgonContainer();

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
        $arguments = $this->createMock(ArgumentMapInterface::class);
        $arguments->method('get')
            ->willReturn([
                'param1' => 'stringValue', // Override for string param
                'param2' => 123            // Override for int param
            ]);

        $container = new ArgonContainer($arguments);

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
        $container = new ArgonContainer();

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
        $arguments = $this->createMock(ArgumentMapInterface::class);
        $arguments->method('get')
            ->willReturn(['param1' => 'override1', 'param2' => 123]);

        $container = new ArgonContainer($arguments);

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
        $container = new ArgonContainer();

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
        $container = new ArgonContainer();
        $instance = $container->get(ClassWithEmptyConstructor::class);

        $this->assertInstanceOf(ClassWithEmptyConstructor::class, $instance);
    }

    /**
     * @throws NotFoundException
     * @throws ContainerException
     */
    public function testMultipleServicesResolution(): void
    {
        $container = new ArgonContainer();

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
        $container = new ArgonContainer();
        $this->assertTrue($container->isResolvable(stdClass::class));
        $this->assertFalse($container->isResolvable('NonExistentClass'));
    }

    /**
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function testExtendOverridesResolvedService(): void
    {
        $container = new ArgonContainer();

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

    /**
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function testResolvesNestedDependencies(): void
    {
        $container = new ArgonContainer();

        // No bindings needed, just pure autowiring magic.
        $instance = $container->get(UserService::class);

        $this->assertInstanceOf(UserService::class, $instance);
    }

    /**
     * @throws ContainerException
     */
    public function testSingletonRegistersServiceWithArguments(): void
    {
        $id = 'MyService';
        $concrete = fn(): object => new stdClass();
        $args = ['foo' => 'bar'];

        $arguments = $this->createMock(ArgumentMapInterface::class);
        $binder = $this->createMock(ServiceBinderInterface::class);

        $arguments->expects($this->once())
            ->method('set')
            ->with($id, $args);

        $binder->expects($this->once())
            ->method('singleton')
            ->with($id, $concrete);

        $container = new ArgonContainer(
            arguments: $arguments,
            binder: $binder
        );

        $result = $container->singleton($id, $concrete, $args);

        $this->assertSame($container, $result);
    }

    public function testGetBindingsDelegatesToBinder(): void
    {
        $id = 'foo';
        $mockDescriptor = $this->createMock(ServiceDescriptorInterface::class);
        $bindings = [$id => $mockDescriptor];

        $binder = $this->createMock(ServiceBinderInterface::class);
        $binder->expects($this->once())
            ->method('getDescriptors')
            ->willReturn($bindings);

        $container = new ArgonContainer(binder: $binder);

        $this->assertSame($bindings, $container->getBindings());
    }

    public function testGetTagsReturnsAllTags(): void
    {
        $mockTags = $this->createMock(\Maduser\Argon\Container\Contracts\TagManagerInterface::class);
        $mockTags->expects($this->once())
            ->method('all')
            ->willReturn(['group1' => ['serviceA', 'serviceB']]);

        $container = new ArgonContainer(tags: $mockTags);

        $this->assertSame(['group1' => ['serviceA', 'serviceB']], $container->getTags());
    }
}
