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
use Tests\Integration\Mocks\InvalidFooFactory;
use Tests\Integration\Mocks\InvokableFactory;
use Tests\Integration\Mocks\Logger;
use Tests\Integration\Mocks\StatefulFooFactory;
use Tests\Integration\Mocks\StaticFooFactory;

final class FactoryIntegrationTest extends TestCase
{
    private ArgonContainer $container;

    #[\Override]
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
        $this->container->set(Foo::class)->factory(FooFactory::class, 'make');

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
        $this->container->set(Foo::class)->factory(InvokableFactory::class);

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
        $this->container->set(Foo::class)->factory(FooFactory::class, 'makeWithArgs');

        $foo = $this->container->get(Foo::class, ['label' => 'custom-arg']);

        $this->assertInstanceOf(Foo::class, $foo);
        $this->assertSame('custom-arg', $foo->label);
    }

    /**
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function testFactoryUsesDefaultValueIfNoArgumentProvided(): void
    {
        $this->container->set(Foo::class)->factory(FooFactory::class, 'makeWithDefault');

        $foo = $this->container->get(Foo::class);

        $this->assertInstanceOf(Foo::class, $foo);
        $this->assertSame('default-label', $foo->label);
    }

    /**
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function testFactoryMethodParametersUseContainerResolution(): void
    {
        $this->container->set(Logger::class);
        $this->container->set(Foo::class)->factory(FooFactory::class, 'makeWithLogger');

        $foo = $this->container->get(Foo::class);

        $this->assertInstanceOf(Foo::class, $foo);
        $this->assertSame(Logger::class, $foo->label);
    }

    /**
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function testFactoryRuntimeArgumentsDoNotConfigureFactoryObject(): void
    {
        $this->container->set(StatefulFooFactory::class, args: ['label' => 'factory-config']);
        $this->container->set(Foo::class)
            ->factory(StatefulFooFactory::class, 'make')
            ->transient();

        $foo = $this->container->get(Foo::class, ['label' => 'product-runtime']);
        $factory = $this->container->get(StatefulFooFactory::class);

        $this->assertSame('factory-config:product-runtime', $foo->label);
        $this->assertSame('factory-config', $factory->label);
        $this->assertSame(1, $factory->calls);
    }

    /**
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function testSharedFactoryObjectIgnoresDifferentProductRuntimeArguments(): void
    {
        $this->container->set(StatefulFooFactory::class, args: ['label' => 'factory-config']);
        $this->container->set(Foo::class)
            ->factory(StatefulFooFactory::class, 'make')
            ->transient();

        $first = $this->container->get(Foo::class, ['label' => 'first-product']);
        $second = $this->container->get(Foo::class, ['label' => 'second-product']);
        $factory = $this->container->get(StatefulFooFactory::class);

        $this->assertSame('factory-config:first-product', $first->label);
        $this->assertSame('factory-config:second-product', $second->label);
        $this->assertSame(2, $factory->calls);
    }

    /**
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function testFactoryThrowsIfRequiredArgumentIsMissing(): void
    {
        $this->container->set(Foo::class)->factory(FooFactory::class, 'makeWithArgs');

        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage("Missing required argument 'label'");

        $this->container->get(Foo::class);
    }

    /**
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function testUseFactoryWithStaticMethod(): void
    {
        $this->container->set(Foo::class)->factory(StaticFooFactory::class, 'createStatic');

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

        $this->container->set(Foo::class)->factory(FooFactory::class, 'missingMethod');

        $this->container->get(Foo::class);
    }

    /**
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function testFactoryReturningNonObjectThrowsContainerException(): void
    {
        $this->container->set(Foo::class)->factory(InvalidFooFactory::class, 'makeString');

        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage('Factory method "' . InvalidFooFactory::class . '::makeString()"');
        $this->expectExceptionMessage('must return an object, got string');

        $this->container->get(Foo::class);
    }

//    public function testThrowsWhenFactoryMethodIsMissing(): void
//    {
//        $descriptor = new ServiceDescriptor('serviceId', Foo::class, false);
//
//        $this->expectException(ContainerException::class);
//        $this->expectExceptionMessage(sprintf(
//            'Factory method "nonexistent" not found on class "%s".',
//            FooFactory::class
//        ));
//
//        $descriptor->setFactory(FooFactory::class, 'nonexistent');
//    }

    public function testUseFactoryThrowsIfClassDoesNotExist(): void
    {
        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage('Factory class or interface "FakeFactoryClass" does not exist');

        $container = new ArgonContainer();

        /**
         * @psalm-suppress ArgumentTypeCoercion
         * @psalm-suppress UndefinedClass
         */
        $container->set(Foo::class)->factory('FakeFactoryClass');
    }
}
