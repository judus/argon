<?php

declare(strict_types=1);

namespace Tests\Integration;

use Maduser\Argon\Container\ArgonContainer;
use Maduser\Argon\Container\Exceptions\ContainerException;
use Maduser\Argon\Container\Exceptions\NotFoundException;
use Maduser\Argon\Container\ServiceDescriptor;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionException;
use Tests\Integration\Mocks\Foo;
use Tests\Integration\Mocks\FooFactory;
use Tests\Integration\Mocks\InvokableFactory;
use Tests\Integration\Mocks\StaticFooFactory;

final class FactoryIntegrationTest extends TestCase
{
    private ArgonContainer $container;

    protected function setUp(): void
    {
        $this->container = new ArgonContainer();
    }

    /**
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function testUseFactoryWithExplicitMethod(): void
    {
        $this->container->bind(Foo::class)->useFactory(FooFactory::class, 'make');

        $foo = $this->container->get(Foo::class);

        $this->assertInstanceOf(Foo::class, $foo);
        $this->assertSame('made-by-factory', $foo->label);
    }

    /**
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function testUseFactoryWithInvokeMethod(): void
    {
        $this->container->bind(Foo::class)->useFactory(InvokableFactory::class);

        $foo = $this->container->get(Foo::class);

        $this->assertInstanceOf(Foo::class, $foo);
        $this->assertSame('from-invokable', $foo->label);
    }

    /**
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function testFactorySupportsArguments(): void
    {
        $this->container->bind(Foo::class)->useFactory(FooFactory::class, 'makeWithArgs');

        $foo = $this->container->get(Foo::class, ['label' => 'custom-arg']);

        $this->assertInstanceOf(Foo::class, $foo);
        $this->assertSame('custom-arg', $foo->label);
    }

    /**
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function testUseFactoryWithStaticMethod(): void
    {
        $this->container->bind(Foo::class)->useFactory(StaticFooFactory::class, 'createStatic');

        $foo = $this->container->get(Foo::class);

        $this->assertInstanceOf(Foo::class, $foo);
        $this->assertSame('from-static-method', $foo->label);
    }

    /**
     * @throws NotFoundException
     */
    public function testUseFactoryWithNonExistentMethodThrows(): void
    {
        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage("Factory method \"missingMethod\" not found on class");

        $this->container->bind(Foo::class)->useFactory(FooFactory::class, 'missingMethod');

        $this->container->get(Foo::class);
    }

    public function testThrowsWhenFactoryMethodIsMissing(): void
    {
        $descriptor = new ServiceDescriptor('serviceId', Foo::class, false);

        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage(sprintf(
            'Factory method "nonexistent" not found on class "%s".',
            FooFactory::class
        ));

        $descriptor->setFactory(FooFactory::class, 'nonexistent');
    }

    public function testUseFactoryThrowsIfClassDoesNotExist(): void
    {
        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage('Factory class "FakeFactoryClass" does not exist');

        $container = new ArgonContainer();

        /**
         * @psalm-suppress ArgumentTypeCoercion
         * @psalm-suppress UndefinedClass
         */
        $container->bind(Foo::class)->useFactory('FakeFactoryClass');
    }
}
