<?php

declare(strict_types=1);

namespace Tests\Unit\Container;

use Maduser\Argon\Container\ArgonContainer;
use Maduser\Argon\Container\Contracts\BindingBuilderInterface;
use Maduser\Argon\Container\Contracts\TagManagerInterface;
use Maduser\Argon\Container\Exceptions\ContainerException;
use Maduser\Argon\Container\ServiceBinder;
use Maduser\Argon\Container\ServiceDescriptor;
use SlevomatCodingStandard\Sniffs\TestCase;
use Tests\Unit\Container\Mocks\Foo;
use Tests\Unit\Container\Mocks\FooFactory;

class BindingBuilderTest extends TestCase
{
    /**
     * @throws ContainerException
     */
    public function testUseFactoryMethodIsCalled(): void
    {
        $container = new ArgonContainer();

        $builder = $container->set(Foo::class);
        $result = $builder->factory(FooFactory::class);

        $this->assertInstanceOf(BindingBuilderInterface::class, $result);
    }

    /**
     * @throws ContainerException
     */
    public function testGetDescriptorReturnsCorrectType(): void
    {
        $binder = new ServiceBinder(
            $this->createMock(TagManagerInterface::class)
        );

        $builder = $binder->set(Foo::class)->transient();

        $descriptor = $builder->getDescriptor();

        $this->assertInstanceOf(ServiceDescriptor::class, $descriptor);
        $this->assertSame(Foo::class, $descriptor->getConcrete());
    }

    public function testSharedMethodMarksDescriptorAsShared(): void
    {
        $binder = new ServiceBinder(
            $this->createMock(TagManagerInterface::class),
            false
        );

        $builder = $binder->set(Foo::class)->shared();

        $this->assertTrue($builder->getDescriptor()->isShared());
    }
}
