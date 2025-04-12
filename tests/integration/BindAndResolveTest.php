<?php

declare(strict_types=1);

namespace Tests\Integration;

use Maduser\Argon\Container\Exceptions\ContainerException;
use Maduser\Argon\Container\Exceptions\NotFoundException;
use Maduser\Argon\Container\ArgonContainer;
use PHPUnit\Framework\TestCase;
use Tests\Integration\Compiler\Mocks\Logger;
use Tests\Integration\Mocks\Bar;
use Tests\Integration\Mocks\Concrete;
use Tests\Integration\Mocks\Foo;
use Tests\Integration\Mocks\FooFactory;
use Tests\Integration\Mocks\InvokableFactory;
use Tests\Integration\Mocks\MyInterface;
use Tests\Integration\Mocks\NeedsSomethingUnresolvable;
use Tests\Integration\Mocks\OtherConcrete;
use Tests\Integration\Mocks\Silent;
use Tests\Integration\Mocks\UsesLogger;

final class BindAndResolveTest extends TestCase
{
    /**
     * @throws NotFoundException
     * @throws ContainerException
     */
    public function testTransientBindingCreatesNewInstances(): void
    {
        $container = new ArgonContainer();
        $container->set(Foo::class)->transient();

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
        $container = new ArgonContainer();
        $container->set(Bar::class);

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
        $container = new ArgonContainer();
        $container->set(MyInterface::class, Concrete::class);

        $service = $container->get(MyInterface::class);

        $this->assertInstanceOf(Concrete::class, $service);
    }

    /**
     * @throws NotFoundException
     * @throws ContainerException
     */
    public function testSingletonBindingWithExplicitConcrete(): void
    {
        $container = new ArgonContainer();
        $container->set(MyInterface::class, Concrete::class);

        $a = $container->get(MyInterface::class);
        $b = $container->get(MyInterface::class);

        $this->assertInstanceOf(Concrete::class, $a);
        $this->assertSame($a, $b);
    }

    public function testBindingWithInvalidConcreteThrows(): void
    {
        $this->expectException(ContainerException::class);

        $container = new ArgonContainer();
        $container->set(MyInterface::class, 'TotallyNotAClass');
    }

    /**
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function testGettingUnboundClassWithoutConstructorSucceeds(): void
    {
        $container = new ArgonContainer();

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

        $container = new ArgonContainer();
        $container->get(NeedsSomethingUnresolvable::class);
    }

    /**
     * @throws NotFoundException
     * @throws ContainerException
     */
    public function testRebindingAServiceOverridesPrevious(): void
    {
        $container = new ArgonContainer();

        $container->set(MyInterface::class, Concrete::class);
        $first = $container->get(MyInterface::class);

        $container->set(MyInterface::class, OtherConcrete::class);
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
        $container = new ArgonContainer();
        $container->set(Foo::class, fn() => new Foo());

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
        $container = new ArgonContainer();
        $container->set(Foo::class, fn() => new Foo())->transient();

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
        $container = new ArgonContainer();
        $container->set(Foo::class, null);

        $instance = $container->get(Foo::class);
        $this->assertInstanceOf(Foo::class, $instance);
    }

    /**
     * @throws ContainerException
     */
    public function testHasReturnsTrueForBoundService(): void
    {
        $container = new ArgonContainer();
        $container->set(Foo::class);

        $this->assertTrue($container->has(Foo::class));
    }

    public function testHasReturnsFalseForUnboundService(): void
    {
        $container = new ArgonContainer();

        $this->assertFalse($container->has('Some\Fake\Thing'));
    }

    public function testIsResolvableReturnsTrueForClassWithNoDeps(): void
    {
        $container = new ArgonContainer();
        $this->assertTrue($container->isResolvable(Silent::class));
    }

    public function testIsResolvableReturnsFalseForUnresolvable(): void
    {
        $container = new ArgonContainer();
        $this->assertFalse($container->isResolvable('Does\Not\Exist'));
    }

    /**
     * @throws NotFoundException
     * @throws ContainerException
     */
    public function testRebindingWithClosureOverridesPrevious(): void
    {
        $container = new ArgonContainer();

        $container->set(MyInterface::class, Concrete::class);
        $a = $container->get(MyInterface::class);

        $container->set(MyInterface::class, fn() => new OtherConcrete());
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
        $container = new ArgonContainer();
        $container->set(Foo::class, Foo::class);

        $this->assertInstanceOf(Foo::class, $container->get(Foo::class));
    }

    /**
     * @throws NotFoundException
     * @throws ContainerException
     */
    public function testReplacesFactoryProperly(): void
    {
        $container = new ArgonContainer();

        $bar = new Bar();

        $container->set('someId', fn() => new Foo());
        $container->set('someId', fn() => $bar);

        $a = $container->get('someId');

        $this->assertSame($a, $bar);
    }

    /**
     * @throws NotFoundException
     */
    public function testBindFailsWithNonExistentConcrete(): void
    {
        $this->expectException(ContainerException::class);

        $container = new ArgonContainer();
        $container->set('InvalidInterface', 'Fake\Concrete\Missing');
        $container->get('InvalidInterface');
    }

    /**
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function testCanTagServiceDuringBindFluently(): void
    {
        $container = new ArgonContainer();

        $container->set(Logger::class)->tag(['loggers', 'debug']);
        $container->set('foo', Foo::class)
            ->tag('loggers')
            ->factory(InvokableFactory::class)
            ->tag('some-other-tag');

        $tagged = $container->getTagged('loggers');

        $this->assertCount(2, $tagged);
        $this->assertInstanceOf(Logger::class, $tagged[0]);
        $this->assertInstanceOf(Foo::class, $tagged[1]);
    }

    /**
     * @throws ContainerException
     */
    public function testBindWithArgumentsCanBeRetrievedViaDescriptor(): void
    {
        $container = new ArgonContainer();

        $container->set(Foo::class, args: ['value' => 'bar']);
        $descriptor = $container->getDescriptor(Foo::class);

        $this->assertTrue($descriptor?->hasArgument('value'));
        $this->assertSame('bar', $descriptor->getArgument('value'));
    }

    public function testDescriptorThrowsIfArgumentNotFound(): void
    {
        $this->expectException(ContainerException::class);

        $container = new ArgonContainer();
        $container->set(Foo::class);

        $container->getDescriptor(Foo::class)?->getArgument('missing');
    }

    /**
     * @throws ContainerException
     */
    public function testSetMethodStoresMethodMap(): void
    {
        $container = new ArgonContainer();

        $container->set(Foo::class);
        $descriptor = $container->getDescriptor(Foo::class);

        $descriptor?->defineInvocation('hello', ['name' => 'string']);

        $this->assertSame(['name' => 'string'], $descriptor?->getInvocation('hello'));
        $this->assertArrayHasKey('hello', (array) $descriptor?->getInvocationMap());
    }
}
