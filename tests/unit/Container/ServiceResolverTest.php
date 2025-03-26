<?php

declare(strict_types=1);

namespace Tests\Unit\Container;

use Maduser\Argon\Container\Contracts\InterceptorRegistryInterface;
use Maduser\Argon\Container\Contracts\ParameterResolverInterface;
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
use Tests\Unit\Container\Mocks\SampleInterface;

class ServiceResolverTest extends TestCase
{
    /** @psalm-suppress PropertyNotSetInConstructor */
    private ServiceBinderInterface&MockObject $binder;
    /** @psalm-suppress PropertyNotSetInConstructor */
    private ReflectionCacheInterface&MockObject $reflectionCache;
    /** @psalm-suppress PropertyNotSetInConstructor */
    private InterceptorRegistryInterface&MockObject $interceptors;
    /** @psalm-suppress PropertyNotSetInConstructor */
    private ParameterResolverInterface&MockObject $parameterResolver;
    /** @psalm-suppress PropertyNotSetInConstructor */
    private ServiceResolverInterface $resolver;

    protected function setUp(): void
    {
        $this->binder = $this->createMock(ServiceBinderInterface::class);
        $this->reflectionCache = $this->createMock(ReflectionCacheInterface::class);
        $this->interceptors = $this->createMock(InterceptorRegistryInterface::class);
        $this->parameterResolver = $this->createMock(ParameterResolverInterface::class);

        $this->resolver = new ServiceResolver(
            $this->binder,
            $this->reflectionCache,
            $this->interceptors,
            $this->parameterResolver
        );
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
        $descriptor->method('isSingleton')->willReturn(true);
        $descriptor->method('getInstance')->willReturn($instance);
        $this->interceptors->expects($this->never())->method('apply');

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
        $descriptor->method('isSingleton')->willReturn(false);
        $descriptor->method('getInstance')->willReturn(null);

        $this->binder->method('getDescriptor')->willReturn($descriptor);

        $this->interceptors->method('apply')->willReturnCallback(fn(object $instance): object => $instance);

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
        $this->interceptors->method('apply')->willReturnCallback(fn(object $instance): object => $instance);

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
        $reflection = new \ReflectionClass($resolver);
        $resolvingProp = $reflection->getProperty('resolving');
        $resolvingProp->setAccessible(true);
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
        $descriptor->method('isSingleton')->willReturn(false);
        $descriptor->method('getInstance')->willReturn(null);

        $this->binder->method('getDescriptor')->willReturn($descriptor);

        $intercepted = new stdClass();
        $this->interceptors->method('apply')->willReturn($intercepted);

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
        $descriptor->method('isSingleton')->willReturn(false);
        $descriptor->method('getInstance')->willReturn(null);

        $this->binder->method('getDescriptor')->willReturn($descriptor);
        $this->reflectionCache->method('get')
            ->willReturn(new \ReflectionClass(SampleInterface::class));

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
        $this->reflectionCache->method('get')->willReturn(new \ReflectionClass(FailsInConstructor::class));

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
}
