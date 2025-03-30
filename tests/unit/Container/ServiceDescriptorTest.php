<?php

declare(strict_types=1);

namespace Tests\Unit\Container;

use Maduser\Argon\Container\ArgumentMap;
use Maduser\Argon\Container\ArgumentResolver;
use Maduser\Argon\Container\ContextualBindings;
use Maduser\Argon\Container\ContextualResolver;
use Maduser\Argon\Container\Contracts\ArgumentResolverInterface;
use Maduser\Argon\Container\Contracts\ContextualResolverInterface;
use Maduser\Argon\Container\Contracts\InterceptorRegistryInterface;
use Maduser\Argon\Container\Contracts\ReflectionCacheInterface;
use Maduser\Argon\Container\Contracts\ServiceBinderInterface;
use Maduser\Argon\Container\Exceptions\ContainerException;
use Maduser\Argon\Container\InterceptorRegistry;
use Maduser\Argon\Container\ReflectionCache;
use Maduser\Argon\Container\ServiceBinder;
use Maduser\Argon\Container\ServiceDescriptor;
use Maduser\Argon\Container\ServiceResolver;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionException;
use stdClass;
use Tests\Unit\Container\Mocks\Foo;
use Tests\Unit\Container\Mocks\FooFactory;

class ServiceDescriptorTest extends TestCase
{
    public function testIdIsSet(): void
    {
        $descriptor = new ServiceDescriptor('serviceId', stdClass::class, true);
        $this->assertEquals('serviceId', $descriptor->getId());
    }

    public function testIsSingletonReturnsTrueWhenSet(): void
    {
        $descriptor = new ServiceDescriptor('serviceId', stdClass::class, true);
        $this->assertTrue($descriptor->isSingleton());
    }

    public function testIsSingletonReturnsFalseWhenNotSet(): void
    {
        $descriptor = new ServiceDescriptor('serviceId', stdClass::class, false);
        $this->assertFalse($descriptor->isSingleton());
    }

    public function testGetConcreteReturnsClassString(): void
    {
        $descriptor = new ServiceDescriptor('serviceId', stdClass::class, true);
        $this->assertSame(stdClass::class, $descriptor->getConcrete());
    }

    public function testGetConcreteReturnsClosure(): void
    {
        $closure = fn(): object => new stdClass();
        $descriptor = new ServiceDescriptor('serviceId', $closure, true);
        $this->assertSame($closure, $descriptor->getConcrete());
    }

    public function testGetInstanceReturnsNullInitially(): void
    {
        $descriptor = new ServiceDescriptor('serviceId', stdClass::class, true);
        $this->assertNull($descriptor->getInstance());
    }

    public function testStoreInstanceOnlyStoresOnceIfSingleton(): void
    {
        $descriptor = new ServiceDescriptor('serviceId', stdClass::class, true);

        $instance1 = new stdClass();
        $instance2 = new stdClass();

        $descriptor->storeInstance($instance1);
        $descriptor->storeInstance($instance2); // should be ignored

        $this->assertSame($instance1, $descriptor->getInstance());
    }

    public function testStoreInstanceHasNoEffectIfNotSingleton(): void
    {
        $descriptor = new ServiceDescriptor('serviceId', stdClass::class, false);
        $descriptor->storeInstance(new stdClass());

        $this->assertNull($descriptor->getInstance(), 'Transient services should not store instance.');
    }

    /**
     * @throws ContainerException
     */
    public function testSetFactoryWithExplicitMethod(): void
    {
        $descriptor = new ServiceDescriptor('serviceId', Foo::class, true);
        $descriptor->setFactory(FooFactory::class, 'make');

        $this->assertTrue($descriptor->hasFactory());
        $this->assertSame(FooFactory::class, $descriptor->getFactoryClass());
        $this->assertSame('make', $descriptor->getFactoryMethod());
    }

    /**
     * @throws ContainerException
     */
    public function testSetFactoryWithInvoke(): void
    {
        $descriptor = new ServiceDescriptor('serviceId', Foo::class, true);
        $descriptor->setFactory(FooFactory::class);

        $this->assertTrue($descriptor->hasFactory());
        $this->assertSame(FooFactory::class, $descriptor->getFactoryClass());
        $this->assertSame('__invoke', $descriptor->getFactoryMethod());
    }

    /**
     * @throws ContainerException
     */
    public function testSetFactoryThrowsForMissingMethod(): void
    {
        $factoryClass = FooFactory::class;
        $method = 'nonexistent';

        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage(
            sprintf(
                'Factory method "%s" not found on class "%s".',
                $method,
                $factoryClass
            ),
        );

        $descriptor = new ServiceDescriptor('serviceId', Foo::class, true);
        $descriptor->setFactory($factoryClass, $method);
    }

    /**
     * @throws ReflectionException
     * @throws ContainerException
     */
    public function testThrowsWhenFactoryClassIsMissing(): void
    {
        $descriptor = new ServiceDescriptor('serviceId', Foo::class, false);
        $descriptor->setFactory(FooFactory::class);

        // Corrupt the factoryClass via reflection
        $ref = new ReflectionClass($descriptor);
        $factoryProp = $ref->getProperty('factoryClass');
        $factoryProp->setValue($descriptor, null);

        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage('Factory class not defined.');

        // Trigger resolution (simulate what resolveFromFactory() would do)
        $resolver = new ServiceResolver(
            binder: $this->createMock(ServiceBinderInterface::class),
            reflectionCache: $this->createMock(ReflectionCacheInterface::class),
            interceptors: $this->createMock(InterceptorRegistryInterface::class),
            argumentResolver: $this->createMock(ArgumentResolverInterface::class)
        );

        $ref = new ReflectionClass($resolver);
        $method = $ref->getMethod('resolveFromFactory');
        $method->invoke($resolver, Foo::class, $descriptor, []);
    }

    /**
     * @throws ReflectionException
     * @throws ContainerException
     */
    public function testResolveFromFactoryThrowsWhenMethodMissing(): void
    {
        $resolver = new ServiceResolver(
            binder: $this->createMock(ServiceBinderInterface::class),
            reflectionCache: $this->createMock(ReflectionCacheInterface::class),
            interceptors: $this->createMock(InterceptorRegistryInterface::class),
            argumentResolver: $this->createMock(ArgumentResolverInterface::class),
        );

        $descriptor = new ServiceDescriptor('serviceId', Foo::class, false);
        $descriptor->setFactory(FooFactory::class);

        // Sabotage factoryMethod
        $ref = new ReflectionClass($descriptor);
        $methodProp = $ref->getProperty('factoryMethod');
        $methodProp->setValue($descriptor, 'nonexistent');

        // Trigger private resolveFromFactory via reflection
        $resolverRef = new ReflectionClass($resolver);
        $method = $resolverRef->getMethod('resolveFromFactory');

        $this->expectException(ContainerException::class);
        $this->expectExceptionMessageMatches('/Factory method "nonexistent" not found/');

        $method->invoke($resolver, Foo::class, $descriptor, []);
    }


}
