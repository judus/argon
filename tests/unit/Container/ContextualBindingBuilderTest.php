<?php

declare(strict_types=1);

namespace Tests\Unit\Container;

use Maduser\Argon\Container\ContextualBindingBuilder;
use Maduser\Argon\Container\Contracts\ContextualBindingsInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ContextualBindingBuilderTest extends TestCase
{
    public function testSetDelegatesToRegistryWithString(): void
    {
        /** @var MockObject&ContextualBindingsInterface $registry */
        $registry = $this->createMock(ContextualBindingsInterface::class);

        $registry->expects($this->once())
            ->method('bind')
            ->with('MyService', 'MyDependency', 'MyConcrete');

        $builder = new ContextualBindingBuilder($registry, 'MyService');
        $builder->bind('MyDependency', 'MyConcrete');
    }

    public function testSetDelegatesToRegistryWithClosure(): void
    {
        /** @var MockObject&ContextualBindingsInterface $registry */
        $registry = $this->createMock(ContextualBindingsInterface::class);

        $closure = fn(): string => 'value';

        $registry->expects($this->once())
            ->method('bind')
            ->with('TargetClass', 'SomeDep', $closure);

        $builder = new ContextualBindingBuilder($registry, 'TargetClass');
        $builder->bind('SomeDep', $closure);
    }
}
