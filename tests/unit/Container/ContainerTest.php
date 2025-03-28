<?php

declare(strict_types=1);

namespace Tests\Unit\Container;

use Maduser\Argon\Container\Container;
use Maduser\Argon\Container\Contracts\ContextualBindingBuilderInterface;
use Maduser\Argon\Container\Contracts\InterceptorInterface;
use Maduser\Argon\Container\Contracts\ArgumentMapInterface;
use Maduser\Argon\Container\Contracts\ParameterStoreInterface;
use Maduser\Argon\Container\Contracts\ServiceDescriptorInterface;
use Maduser\Argon\Container\Exceptions\ContainerException;
use Maduser\Argon\Container\Exceptions\NotFoundException;
use Maduser\Argon\Container\ParameterStore;
use Maduser\Argon\Container\ServiceContainer;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use stdClass;
use Tests\Unit\Container\Mocks\SampleProvider;

class ContainerTest extends TestCase
{
    /** @psalm-suppress PropertyNotSetInConstructor */
    private ServiceContainer&MockObject $mockContainer;

    protected function setUp(): void
    {
        $this->mockContainer = $this->createMock(ServiceContainer::class);
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
        $this->mockContainer->expects($this->once())
            ->method('bind')
            ->with('foo', null, false);

        Container::bind('foo');
    }

    /**
     * @throws ContainerException
     */
    public function testSingletonDelegatesToContainer(): void
    {
        $this->mockContainer->expects($this->once())
            ->method('singleton')
            ->with('bar');

        Container::singleton('bar');
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
            ->with('MyClass', 'myMethod', ['param' => 'value'])
            ->willReturn($result);

        $this->assertSame($result, Container::invoke('MyClass', 'myMethod', ['param' => 'value']));
    }

    /**
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function testRegisterProviderDelegatesToContainer(): void
    {
        $this->mockContainer->expects($this->once())
            ->method('registerProvider')
            ->with(SampleProvider::class);

        Container::registerProvider(SampleProvider::class);
    }

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
            ->with(InterceptorInterface::class);

        Container::registerInterceptor(InterceptorInterface::class);
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

    public function testParametersReturnsStore(): void
    {
        $parameters = $this->createMock(ParameterStoreInterface::class);
        $this->mockContainer->expects($this->once())
            ->method('getParameters')
            ->willReturn($parameters);

        $this->assertSame($parameters, Container::parameters());
    }
}
