<?php

declare(strict_types=1);

namespace Tests\Integration;

use Maduser\Argon\Container\Exceptions\ContainerException;
use Maduser\Argon\Container\Exceptions\NotFoundException;
use Maduser\Argon\Container\ServiceContainer;
use PHPUnit\Framework\TestCase;
use Tests\Integration\Mocks\Bar;
use Tests\Integration\Mocks\Concrete;
use Tests\Integration\Mocks\Foo;
use Tests\Integration\Mocks\MyInterface;
use Tests\Integration\Mocks\NeedsSomethingUnresolvable;
use Tests\Integration\Mocks\OtherConcrete;
use Tests\Integration\Mocks\Silent;

final class BindAndResolveTest extends TestCase
{
    /**
     * @throws NotFoundException
     * @throws ContainerException
     */
    public function testTransientBindingCreatesNewInstances(): void
    {
        $container = new ServiceContainer();
        $container->bind(Foo::class);

        $a = $container->get(Foo::class);
        $b = $container->get(Foo::class);

        $this->assertInstanceOf(Foo::class, $a);
        $this->assertNotSame($a, $b, 'Transient should return different instances');
    }

    /**
     * @throws NotFoundException
     * @throws ContainerException
     */
    public function testSingletonBindingReturnsSameInstance(): void
    {
        $container = new ServiceContainer();
        $container->singleton(Bar::class);

        $a = $container->get(Bar::class);
        $b = $container->get(Bar::class);

        $this->assertSame($a, $b, 'Singleton should return same instance');
    }

    /**
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function testBindingConcreteClassToAbstract(): void
    {
        $container = new ServiceContainer();
        $container->bind(MyInterface::class, Concrete::class);

        $service = $container->get(MyInterface::class);

        $this->assertInstanceOf(Concrete::class, $service);
    }

    /**
     * @throws NotFoundException
     * @throws ContainerException
     */
    public function testSingletonBindingWithExplicitConcrete(): void
    {
        $container = new ServiceContainer();
        $container->singleton(MyInterface::class, Concrete::class);

        $a = $container->get(MyInterface::class);
        $b = $container->get(MyInterface::class);

        $this->assertInstanceOf(Concrete::class, $a);
        $this->assertSame($a, $b);
    }

    public function testBindingWithInvalidConcreteThrows(): void
    {
        $this->expectException(ContainerException::class);

        $container = new ServiceContainer();
        $container->bind(MyInterface::class, 'TotallyNotAClass');
    }

    /**
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function testGettingUnboundClassWithoutConstructorSucceeds(): void
    {
        $container = new ServiceContainer();

        $instance = $container->get(Silent::class);
        $this->assertInstanceOf(Silent::class, $instance);
    }

    /**
     * @throws NotFoundException
     * @throws ContainerException
     */
    public function testGettingUnboundClassWithMissingDepsThrows(): void
    {
        $this->expectException(NotFoundException::class);

        $container = new ServiceContainer();
        $container->get(NeedsSomethingUnresolvable::class);
    }

    /**
     * @throws NotFoundException
     * @throws ContainerException
     */
    public function testRebindingAServiceOverridesPrevious(): void
    {
        $container = new ServiceContainer();

        $container->singleton(MyInterface::class, Concrete::class);
        $first = $container->get(MyInterface::class);

        $container->singleton(MyInterface::class, OtherConcrete::class);
        $second = $container->get(MyInterface::class);

        $this->assertInstanceOf(OtherConcrete::class, $second);
        $this->assertNotSame($first, $second);
    }

    /**
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function testRegisterFactoryAsSingleton(): void
    {
        $container = new ServiceContainer();
        $container->registerFactory(Foo::class, fn() => new Foo());

        $a = $container->get(Foo::class);
        $b = $container->get(Foo::class);

        $this->assertInstanceOf(Foo::class, $a);
        $this->assertSame($a, $b);
    }

    /**
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function testRegisterFactoryAsTransient(): void
    {
        $container = new ServiceContainer();
        $container->registerFactory(Foo::class, fn() => new Foo(), isSingleton: false);

        $a = $container->get(Foo::class);
        $b = $container->get(Foo::class);

        $this->assertNotSame($a, $b);
    }

    /**
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function testBindWithNullConcreteInfersConcreteFromId(): void
    {
        $container = new ServiceContainer();
        $container->bind(Foo::class, null); // same as $container->bind(Foo::class);

        $instance = $container->get(Foo::class);
        $this->assertInstanceOf(Foo::class, $instance);
    }

    /**
     * @throws ContainerException
     */
    public function testHasReturnsTrueForBoundService(): void
    {
        $container = new ServiceContainer();
        $container->singleton(Foo::class);

        $this->assertTrue($container->has(Foo::class));
    }

    public function testHasReturnsFalseForUnboundService(): void
    {
        $container = new ServiceContainer();

        $this->assertFalse($container->has('Some\Fake\Thing'));
    }

    public function testIsResolvableReturnsTrueForClassWithNoDeps(): void
    {
        $container = new ServiceContainer();
        $this->assertTrue($container->isResolvable(Silent::class));
    }

    public function testIsResolvableReturnsFalseForUnresolvable(): void
    {
        $container = new ServiceContainer();
        $this->assertFalse($container->isResolvable('Does\Not\Exist'));
    }

    /**
     * @throws NotFoundException
     * @throws ContainerException
     */
    public function testRebindingWithClosureOverridesPrevious(): void
    {
        $container = new ServiceContainer();

        $container->bind(MyInterface::class, Concrete::class);
        $a = $container->get(MyInterface::class);

        $container->bind(MyInterface::class, fn() => new OtherConcrete());
        $b = $container->get(MyInterface::class);

        $this->assertInstanceOf(OtherConcrete::class, $b);
        $this->assertNotSame($a, $b);
    }

    /**
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function testBindWithItselfAsConcrete(): void
    {
        $container = new ServiceContainer();
        $container->bind(Foo::class, Foo::class);

        $this->assertInstanceOf(Foo::class, $container->get(Foo::class));
    }

    /**
     * @throws NotFoundException
     * @throws ContainerException
     */
    public function testSingletonReplacesFactoryProperly(): void
    {
        $container = new ServiceContainer();

        $container->registerFactory(Foo::class, fn() => new Foo(), false);
        $container->singleton(Foo::class, fn() => new Foo());

        $a = $container->get(Foo::class);
        $b = $container->get(Foo::class);

        $this->assertSame($a, $b);
    }

    /**
     * @throws NotFoundException
     */
    public function testBindFailsWithNonExistentConcrete(): void
    {
        $this->expectException(ContainerException::class);

        $container = new ServiceContainer();
        $container->bind('InvalidInterface', 'Fake\Concrete\Missing');
        $container->get('InvalidInterface');
    }
}
