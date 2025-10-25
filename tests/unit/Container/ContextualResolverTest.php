<?php

declare(strict_types=1);

namespace Tests\Unit\Container;

use Maduser\Argon\Container\ContextualResolver;
use Maduser\Argon\Container\Contracts\ContextualBindingsInterface;
use Maduser\Argon\Container\ContextualBindingBuilder;
use Maduser\Argon\Container\Exceptions\ContainerException;
use Maduser\Argon\Container\Exceptions\NotFoundException;
use Maduser\Argon\Container\ArgonContainer;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use stdClass;

final class ContextualResolverTest extends TestCase
{
    /** @psalm-suppress PropertyNotSetInConstructor */
    private ArgonContainer&MockObject $container;
    /** @psalm-suppress PropertyNotSetInConstructor */
    private ContextualBindingsInterface&MockObject $registry;
    /** @psalm-suppress PropertyNotSetInConstructor */
    private ContextualResolver $resolver;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->container = $this->createMock(ArgonContainer::class);
        $this->registry = $this->createMock(ContextualBindingsInterface::class);
        $this->resolver = new ContextualResolver($this->container, $this->registry);
    }

    public function testHasReturnsTrueWhenBindingExists(): void
    {
        $this->registry->method('has')->with('Consumer', 'Dependency')->willReturn(true);

        $this->assertTrue($this->resolver->has('Consumer', 'Dependency'));
    }

    public function testHasReturnsFalseWhenBindingMissing(): void
    {
        $this->registry->method('has')->with('Consumer', 'Dependency')->willReturn(false);

        $this->assertFalse($this->resolver->has('Consumer', 'Dependency'));
    }

    /**
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function testResolveReturnsClosureResult(): void
    {
        $closure = fn(): object => new stdClass();

        $this->registry->method('get')->with('Consumer', 'Dep')->willReturn($closure);

        $result = $this->resolver->resolve('Consumer', 'Dep');

        $this->assertInstanceOf(stdClass::class, $result);
    }

    /**
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function testResolveCallsContainerWhenStringBinding(): void
    {
        $this->registry->method('get')->with('Consumer', 'Dep')->willReturn('ResolvedClass');

        $object = new stdClass();
        $this->container->expects($this->once())->method('get')->with('ResolvedClass')->willReturn($object);

        $this->assertSame($object, $this->resolver->resolve('Consumer', 'Dep'));
    }

    /**
     * @throws ContainerException
     */
    public function testResolveThrowsNotFoundExceptionWhenMissing(): void
    {
        $this->registry->method('get')->with('Consumer', 'Dep')->willReturn(null);

        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage("No contextual binding found for 'Dep' in 'Consumer'");

        $this->resolver->resolve('Consumer', 'Dep');
    }

    public function testForReturnsContextualBindingBuilder(): void
    {
        $builder = $this->resolver->for('Target');

        $this->assertInstanceOf(ContextualBindingBuilder::class, $builder);
    }
}
