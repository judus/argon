<?php

declare(strict_types=1);

namespace Tests\Unit\Container;

use Maduser\Argon\Container\ArgonContainer;
use Maduser\Argon\Container\BindingBuilder;
use Maduser\Argon\Container\Container;
use Maduser\Argon\Container\Contracts\ArgumentMapInterface;
use Maduser\Argon\Container\Contracts\ContextualBindingBuilderInterface;
use Maduser\Argon\Container\Contracts\ContextualBindingsInterface;
use Maduser\Argon\Container\Contracts\ParameterStoreInterface;
use Maduser\Argon\Container\Contracts\ServiceDescriptorInterface;
use Maduser\Argon\Container\Contracts\TagManagerInterface;
use Maduser\Argon\Container\Exceptions\ContainerException;
use Maduser\Argon\Container\Exceptions\NotFoundException;
use Maduser\Argon\Container\Interceptors\Post\ValidationInterceptor;
use Maduser\Argon\Container\ServiceBinder;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use stdClass;
use Tests\Unit\Container\Mocks\SampleProvider;
use Tests\Unit\Container\Mocks\SomeClass;

class ContainerTest extends TestCase
{
    /** @psalm-suppress PropertyNotSetInConstructor */
    private ArgonContainer&MockObject $mockContainer;

    protected function setUp(): void
    {
        $this->mockContainer = $this->createMock(ArgonContainer::class);
        Container::set($this->mockContainer);
    }

    public function testSetAndInstanceReturnsContainer(): void
    {
        $this->assertSame($this->mockContainer, Container::instance());
    }

    public function testHasDelegatesToContainer(): void
    {
        $this->mockContainer->expects($this->once())
            ->method('has')
            ->with('foo')
            ->willReturn(true);

        $this->assertTrue(Container::has('foo'));
    }

    public function testIsResolvableDelegatesToContainer(): void
    {
        $this->mockContainer->expects($this->once())
            ->method('isResolvable')
            ->with('MyClass')
            ->willReturn(true);

        $this->assertTrue(Container::isResolvable('MyClass'));
    }

    /**
     * @throws ContainerException
     */
    public function testBindDelegatesToContainer(): void
    {
        $binder = new ServiceBinder(
            $this->createMock(TagManagerInterface::class)
        );

        $builder = $binder->singleton(SomeClass::class);

        $this->mockContainer
            ->expects($this->once())
            ->method('bind')
            ->with(SomeClass::class)
            ->willReturn($builder);

        $result = Container::bind(SomeClass::class);

        $this->assertInstanceOf(BindingBuilder::class, $result);
    }

    /**
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function testSingletonDelegatesToContainer(): void
    {
        $binder = new ServiceBinder(
            $this->createMock(TagManagerInterface::class)
        );

        $container = new ArgonContainer(binder: $binder);

        Container::set($container);

        $result = Container::singleton(SomeClass::class);

        $this->assertInstanceOf(BindingBuilder::class, $result);

        $resolved = Container::get(SomeClass::class);
        $this->assertInstanceOf(SomeClass::class, $resolved);
    }

    public function testRegisterFactoryDelegatesToContainer(): void
    {
        $factory = fn(): object => new stdClass();

        $this->mockContainer->expects($this->once())
            ->method('registerFactory')
            ->with('myService', $factory, true);

        Container::registerFactory('myService', $factory);
    }

    /**
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function testGetDelegatesToContainer(): void
    {
        $obj = new stdClass();
        $this->mockContainer->expects($this->once())
            ->method('get')
            ->with('someService')
            ->willReturn($obj);

        $this->assertSame($obj, Container::get('someService'));
    }

    /**
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function testOptionalDelegatesToContainer(): void
    {
        $dummy = new stdClass();
        $this->mockContainer->expects($this->once())
            ->method('optional')
            ->with('maybeService')
            ->willReturn($dummy);

        $this->assertSame($dummy, Container::optional('maybeService'));
    }

    /**
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function testInvokeDelegatesToContainer(): void
    {
        $result = 'worked';
        $this->mockContainer->expects($this->once())
            ->method('invoke')
            ->with(['MyClass', 'myMethod'], ['param' => 'value'])
            ->willReturn($result);

        $this->assertSame($result, Container::invoke(['MyClass', 'myMethod'], ['param' => 'value']));
    }

    /**
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function testRegisterProviderDelegatesToContainer(): void
    {
        $this->mockContainer->expects($this->once())
            ->method('register')
            ->with(SampleProvider::class);

        Container::registerProvider(SampleProvider::class);
    }

    /**
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function testBootDelegatesToContainer(): void
    {
        $this->mockContainer->expects($this->once())->method('boot');

        Container::boot();
    }

    /**
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function testExtendDelegatesToContainer(): void
    {
        $decorator = fn(object $obj): object => $obj;

        $this->mockContainer->expects($this->once())
            ->method('extend')
            ->with('service', $decorator);

        Container::extend('service', $decorator);
    }

    /**
     * @throws ContainerException
     */
    public function testRegisterInterceptorDelegatesToContainer(): void
    {
        $this->mockContainer->expects($this->once())
            ->method('registerInterceptor')
            ->with(ValidationInterceptor::class);

        Container::registerInterceptor(ValidationInterceptor::class);
    }

    public function testTagDelegatesToContainer(): void
    {
        $this->mockContainer->expects($this->once())
            ->method('tag')
            ->with('id', ['tag1']);

        Container::tag('id', ['tag1']);
    }

    /**
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function testTaggedDelegatesToContainer(): void
    {
        $expected = [new stdClass()];
        $this->mockContainer->expects($this->once())
            ->method('getTagged')
            ->with('group')
            ->willReturn($expected);

        $this->assertSame($expected, Container::tagged('group'));
    }

    public function testForReturnsBindingBuilder(): void
    {
        $builder = $this->createMock(ContextualBindingBuilderInterface::class);

        $this->mockContainer->expects($this->once())
            ->method('for')
            ->with('TargetClass')
            ->willReturn($builder);

        $this->assertSame($builder, Container::for('TargetClass'));
    }

    public function testBindingsDelegatesToContainer(): void
    {
        $bindings = ['foo' => $this->createMock(ServiceDescriptorInterface::class)];

        $this->mockContainer->expects($this->once())
            ->method('getBindings')
            ->willReturn($bindings);

        $this->assertSame($bindings, Container::bindings());
    }

    public function testPreInterceptorsDelegatesToContainer(): void
    {
        $this->mockContainer->expects($this->once())
            ->method('getPreInterceptors')
            ->willReturn(['SomeInterceptor']);

        $this->assertSame(['SomeInterceptor'], Container::preInterceptors());
    }
    public function testPostInterceptorsDelegatesToContainer(): void
    {
        $this->mockContainer->expects($this->once())
            ->method('getPostInterceptors')
            ->willReturn(['SomeInterceptor']);

        $this->assertSame(['SomeInterceptor'], Container::postInterceptors());
    }

    public function testTagsDelegatesToContainer(): void
    {
        $tags = ['tag1' => ['service1']];
        $this->mockContainer->expects($this->once())
            ->method('getTags')
            ->willReturn($tags);

        $this->assertSame($tags, Container::tags());
    }

    public function testArgumentsDelegatesToContainer(): void
    {
        $arguments = $this->createMock(ArgumentMapInterface::class);
        $this->mockContainer->expects($this->once())
            ->method('getArgumentMap')
            ->willReturn($arguments);

        $this->assertSame($arguments, Container::arguments());
    }

    public function testParametersReturnsStore(): void
    {
        $parameters = $this->createMock(ParameterStoreInterface::class);
        $this->mockContainer->expects($this->once())
            ->method('getParameters')
            ->willReturn($parameters);

        $this->assertSame($parameters, Container::parameters());
    }

    public function testContextualDelegatesToContainer(): void
    {
        $bindings = $this->createMock(ContextualBindingsInterface::class);
        $this->mockContainer->expects($this->once())
            ->method('getContextualBindings')
            ->willReturn($bindings);

        $this->assertSame($bindings, Container::contextualBindings());
    }
}
