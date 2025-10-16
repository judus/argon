<?php

declare(strict_types=1);

namespace Tests\Unit\Container;

use Maduser\Argon\Container\Contracts\InterceptorRegistryInterface;
use Maduser\Argon\Container\Contracts\ArgumentResolverInterface;
use Maduser\Argon\Container\Contracts\ReflectionCacheInterface;
use Maduser\Argon\Container\Contracts\ServiceBinderInterface;
use Maduser\Argon\Container\Contracts\ServiceDescriptorInterface;
use Maduser\Argon\Container\Contracts\ServiceResolverInterface;
use Maduser\Argon\Container\Exceptions\ContainerException;
use Maduser\Argon\Container\Exceptions\NotFoundException;
use Maduser\Argon\Container\ServiceResolver;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionException;
use stdClass;
use Tests\Unit\Container\Mocks\ExplodingConstructor;
use Tests\Unit\Container\Mocks\FailsInConstructor;
use Tests\Unit\Container\Mocks\NonInstantiableClass;
use Tests\Unit\Container\Mocks\SampleInterface;
use Tests\Unit\Container\Mocks\SomeClass;

class ServiceResolverTest extends TestCase
{
    /** @psalm-suppress PropertyNotSetInConstructor */
    private ServiceBinderInterface&MockObject $binder;
    /** @psalm-suppress PropertyNotSetInConstructor */
    private ReflectionCacheInterface&MockObject $reflectionCache;
    /** @psalm-suppress PropertyNotSetInConstructor */
    private InterceptorRegistryInterface&MockObject $interceptors;
    /** @psalm-suppress PropertyNotSetInConstructor */
    private ArgumentResolverInterface&MockObject $parameterResolver;
    /** @psalm-suppress PropertyNotSetInConstructor */
    private ServiceResolverInterface $resolver;

    protected function setUp(): void
    {
        $this->binder = $this->createMock(ServiceBinderInterface::class);
        $this->reflectionCache = $this->createMock(ReflectionCacheInterface::class);
        $this->interceptors = $this->createMock(InterceptorRegistryInterface::class);
        $this->parameterResolver = $this->createMock(ArgumentResolverInterface::class);

        $this->resolver = new ServiceResolver(
            $this->binder,
            $this->reflectionCache,
            $this->interceptors,
            $this->parameterResolver
        );
    }

    /**
     * @throws ReflectionException
     */
    protected function invokeMethod(object $object, string $methodName, array $parameters = []): mixed
    {
        $reflection = new ReflectionClass($object);
        $method = $reflection->getMethod($methodName);

        return $method->invokeArgs($object, $parameters);
    }

    /**
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function testResolvesBoundSingleton(): void
    {
        $descriptor = $this->createMock(ServiceDescriptorInterface::class);
        $instance = new stdClass();

        $this->binder->method('getDescriptor')->willReturn($descriptor);
        $descriptor->method('isShared')->willReturn(true);
        $descriptor->method('getInstance')->willReturn($instance);
        $this->interceptors->expects($this->never())->method('matchPost');

        $result = $this->resolver->resolve('singletonService');

        $this->assertSame($instance, $result);
    }

    /**
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function testResolvesClosureConcrete(): void
    {
        $closure = fn(): object => new stdClass();

        $descriptor = $this->createMock(ServiceDescriptorInterface::class);
        $descriptor->method('getConcrete')->willReturn($closure);
        $descriptor->method('isShared')->willReturn(false);
        $descriptor->method('getInstance')->willReturn(null);

        $this->binder->method('getDescriptor')->willReturn($descriptor);

        $this->interceptors->method('matchPost')->willReturnCallback(fn(object $instance): object => $instance);

        $result = $this->resolver->resolve('closureService');

        $this->assertInstanceOf(stdClass::class, $result);
    }

    /**
     * @throws ContainerException
     */
    public function testThrowsNotFoundForUnknownClass(): void
    {
        $this->binder->method('getDescriptor')->willReturn(null);

        $this->expectException(NotFoundException::class);
        $this->resolver->resolve('NonExistentService');
    }

    /**
     * @throws NotFoundException
     */
    public function testThrowsForNonInstantiableClass(): void
    {
        $this->binder->method('getDescriptor')->willReturn(null);

        eval('abstract class AbstractClassTest {}');

        /**
         * @psalm-suppress ArgumentTypeCoercion
         * @psalm-suppress UndefinedClass
         */
        $this->reflectionCache->method('get')->willReturn(new ReflectionClass('AbstractClassTest'));

        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage("is not instantiable");

        $this->resolver->resolve('AbstractClassTest');
    }

    /**
     * @throws ReflectionException
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function testResolvesClassWithEmptyConstructor(): void
    {
        $class = new class {
        };

        $className = get_class($class);
        $reflection = new ReflectionClass($className);

        $this->binder->method('getDescriptor')->willReturn(null);
        $this->reflectionCache->method('get')->willReturn($reflection);
        $this->interceptors->method('matchPost')->willReturnCallback(fn(object $instance): object => $instance);

        $instance = $this->resolver->resolve($className);

        $this->assertInstanceOf($className, $instance);
    }

    /**
     * @throws NotFoundException
     */
    public function testCircularDependencyThrowsException(): void
    {
        $resolver = new ServiceResolver(
            $this->binder,
            $this->reflectionCache,
            $this->interceptors,
            $this->parameterResolver
        );

        // Fake a recursive resolution by manually pushing to the "resolving" stack
        $reflection = new ReflectionClass($resolver);
        $resolvingProp = $reflection->getProperty('resolving');
        $resolvingProp->setValue($resolver, ['ServiceA' => true]);

        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage("Circular dependency detected");

        $resolver->resolve('ServiceA');
    }

    /**
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function testInterceptorIsApplied(): void
    {
        $closure = fn(): object => new stdClass();

        $descriptor = $this->createMock(ServiceDescriptorInterface::class);
        $descriptor->method('getConcrete')->willReturn($closure);
        $descriptor->method('isShared')->willReturn(false);
        $descriptor->method('getInstance')->willReturn(null);

        $this->binder->method('getDescriptor')->willReturn($descriptor);

        $intercepted = new stdClass();
        $this->interceptors->method('matchPost')->willReturn($intercepted);

        $result = $this->resolver->resolve('interceptedService');

        $this->assertSame($intercepted, $result);
    }

    /**
     * @throws NotFoundException
     */
    public function testThrowsForInterface(): void
    {
        $descriptor = $this->createMock(ServiceDescriptorInterface::class);
        $descriptor->method('getConcrete')->willReturn(SampleInterface::class);
        $descriptor->method('isShared')->willReturn(false);
        $descriptor->method('getInstance')->willReturn(null);

        $this->binder->method('getDescriptor')->willReturn($descriptor);
        $this->reflectionCache->method('get')
            ->willReturn(new ReflectionClass(SampleInterface::class));

        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage('Cannot instantiate interface');

        $this->resolver->resolve(SampleInterface::class);
    }

    /**
     * @throws NotFoundException
     */
    public function testThrowsForDirectInstantiationFailure(): void
    {
        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage(
            "Failed to instantiate '" . FailsInConstructor::class . "' with resolved dependencies."
        );

        $this->binder->method('getDescriptor')->willReturn(null);
        $this->reflectionCache->method('get')->willReturn(new ReflectionClass(FailsInConstructor::class));

        $this->resolver->resolve(FailsInConstructor::class);
    }

    /**
     * @throws ReflectionException
     * @throws NotFoundException
     */
    public function testThrowsForInstantiationFailureWithDependencies(): void
    {
        $className = ExplodingConstructor::class;
        $reflection = new ReflectionClass($className);

        $this->binder->method('getDescriptor')->willReturn(null);
        $this->reflectionCache->method('get')->willReturn($reflection);
        $this->parameterResolver->method('resolve')->willReturn('test');

        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage(
            "Failed to instantiate '$className' with resolved dependencies."
        );

        $this->resolver->resolve($className);
    }

    /**
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function testPreResolutionInterceptorIsApplied(): void
    {
        $id = 'SomeService';
        $parameters = ['param' => 'value'];
        $expectedInstance = new stdClass();

        $this->interceptors->method('matchPre')
            ->with($id, $parameters)
            ->willReturn($expectedInstance);

        $result = $this->resolver->resolve($id, $parameters);

        $this->assertSame($expectedInstance, $result);
    }

    /**
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function testResolvesServiceDefinedAsClosure(): void
    {
        $id = 'ClosureService';
        $expectedInstance = new stdClass();

        $descriptor = $this->createMock(ServiceDescriptorInterface::class);
        $descriptor->method('getConcrete')
            ->willReturn(fn() => $expectedInstance);
        $descriptor->method('isShared')
            ->willReturn(false);
        $descriptor->method('getInstance')
            ->willReturn(null);

        $this->binder->method('getDescriptor')
            ->with($id)
            ->willReturn($descriptor);

        $this->interceptors->method('matchPost')
            ->willReturnArgument(0);

        $result = $this->resolver->resolve($id);

        $this->assertSame($expectedInstance, $result);
    }

    /**
     * @throws ReflectionException
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function testResolvesAliasedService(): void
    {
        eval('class ConcreteService {}');
        $abstractId = 'AbstractService';
        $concreteClass = 'ConcreteService';

        $descriptor = $this->createMock(ServiceDescriptorInterface::class);
        $descriptor->method('getConcrete')
            ->willReturn($concreteClass);
        $descriptor->method('isShared')
            ->willReturn(false);
        $descriptor->method('getInstance')
            ->willReturn(null);
        $descriptor->method('getArguments')
            ->willReturn([]);

        $this->binder->method('getDescriptor')->willReturnCallback(
            fn(string $id) => in_array($id, ['AbstractService', 'ConcreteService'], true) ? $descriptor : null
        );

        /** @psalm-suppress ArgumentTypeCoercion */
        $instance = new ReflectionClass($concreteClass);

        $this->reflectionCache->method('get')
            ->with($concreteClass)
            ->willReturn($instance);

        $this->interceptors->method('matchPost')
            ->willReturnArgument(0);

        $result = $this->resolver->resolve($abstractId);

        /** @psalm-suppress ArgumentTypeCoercion */
        $this->assertInstanceOf($concreteClass, $result);
    }

    /**
     * @throws ReflectionException
     * @throws NotFoundException
     */
    public function testThrowsForAbstractClass(): void
    {
        $abstractClass = 'AbstractClass';
        eval('abstract class AbstractClass {}');

        $this->binder->method('getDescriptor')
            ->willReturn(null);

        /** @psalm-suppress ArgumentTypeCoercion */
        $instance = new ReflectionClass($abstractClass);

        $this->reflectionCache->method('get')
            ->with($abstractClass)
            ->willReturn($instance);

        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage("is not instantiable");

        $this->resolver->resolve($abstractClass);
    }

    /**
     * @throws ReflectionException
     */
    public function testResolveClassExecutesClosure(): void
    {
        $className = 'SomeClass';
        $expectedInstance = new stdClass();

        $descriptor = $this->createMock(ServiceDescriptorInterface::class);
        $descriptor->method('getConcrete')->willReturn(fn() => $expectedInstance);

        $this->binder->method('getDescriptor')->with($className)->willReturn($descriptor);

        $result = $this->invokeMethod($this->resolver, 'resolveClass', [$className]);

        $this->assertSame($expectedInstance, $result);
    }

    /**
     * @throws ReflectionException
     */
    public function testResolveClassRecursivelyFollowsAlias(): void
    {
        $abstractAlias = 'AliasClass';
        $concreteClass = SomeClass::class;

        $descriptor = $this->createMock(ServiceDescriptorInterface::class);
        $descriptor->method('getConcrete')->willReturn($concreteClass);
        $descriptor->method('getArguments')->willReturn([]);

        // First call returns descriptor for the alias
        // Second call returns null to fall through to class reflection
        $expected = [$abstractAlias, $concreteClass];
        $callCount = 0;

        $this->binder->method('getDescriptor')
            ->willReturnCallback(function (string $id) use (&$callCount, $expected, $descriptor) {
                if (!isset($expected[$callCount])) {
                    throw new \RuntimeException("Unexpected call #$callCount to getDescriptor('$id')");
                }

                TestCase::assertSame($expected[$callCount], $id);
                $callCount++;

                return $callCount === 1 ? $descriptor : null;
            });

        $this->reflectionCache->expects($this->once())
            ->method('get')
            ->with($concreteClass)
            ->willReturn(new ReflectionClass($concreteClass));

        // Skip interceptors, just assert we got an instance
        $instance = $this->invokeMethod($this->resolver, 'resolveClass', [$abstractAlias]);

        $this->assertInstanceOf(SomeClass::class, $instance);
    }


    /**
     * @throws ReflectionException
     */
    public function testResolveClassThrowsForNonInstantiable(): void
    {
        $className = NonInstantiableClass::class;

        $this->binder->method('getDescriptor')->with($className)->willReturn(null);
        $this->reflectionCache->method('get')->with($className)->willReturn(new ReflectionClass($className));

        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage("Class '$className' (requested as '$className') is not instantiable.");

        $this->invokeMethod($this->resolver, 'resolveClass', [$className]);
    }

    /**
     * @throws ReflectionException
     */
    public function testResolveClassCatchesInstantiationFailure(): void
    {
        $className = FailsInConstructor::class;

        $this->binder->method('getDescriptor')->with($className)->willReturn(null);
        $this->reflectionCache->method('get')->with($className)->willReturn(new ReflectionClass($className));

        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage("Failed to instantiate '$className' with resolved dependencies.");

        $this->invokeMethod($this->resolver, 'resolveClass', [$className]);
    }

    /**
     * @throws NotFoundException
     */
    public function testStrictModeThrowsForUnregisteredClass(): void
    {
        $resolver = new ServiceResolver(
            $this->binder,
            $this->reflectionCache,
            $this->interceptors,
            $this->parameterResolver,
            true
        );

        $this->binder->method('getDescriptor')->willReturn(null);

        $this->expectException(NotFoundException::class);
        $resolver->resolve(stdClass::class);
    }

    /**
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function testStrictModeResolvesBoundService(): void
    {
        $descriptor = $this->createMock(ServiceDescriptorInterface::class);
        $descriptor->method('getConcrete')->willReturn(fn() => new stdClass());
        $descriptor->method('isShared')->willReturn(false);
        $descriptor->method('getInstance')->willReturn(null);

        $this->binder->method('getDescriptor')->willReturn($descriptor);
        $this->interceptors->method('matchPost')->willReturnCallback(fn(object $instance): object => $instance);

        $resolver = new ServiceResolver(
            $this->binder,
            $this->reflectionCache,
            $this->interceptors,
            $this->parameterResolver,
            true
        );

        $result = $resolver->resolve('boundService');

        $this->assertInstanceOf(stdClass::class, $result);
    }

    public function testResolveWrapsReflectionExceptionFromDescriptor(): void
    {
        $descriptor = $this->createMock(ServiceDescriptorInterface::class);
        $descriptor->method('isShared')->willReturn(false);
        $descriptor->method('getInstance')->willReturn(null);
        $descriptor->method('hasFactory')->willReturn(false);
        $descriptor->method('getConcrete')->willReturn('SomeMissingClass');

        $this->binder->method('getDescriptor')->willReturn($descriptor);
        $this->interceptors->method('matchPre')->willReturn(null);
        $this->reflectionCache
            ->method('get')
            ->willThrowException(new ReflectionException('Mocked reflection failure.'));

        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage('Reflection error: Mocked reflection failure.');

        $this->resolver->resolve('serviceWithReflectionFailure');
    }
}
