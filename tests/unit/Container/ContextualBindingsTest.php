<?php

declare(strict_types=1);

namespace Tests\Unit\Container;

use Closure;
use Maduser\Argon\Container\ContextualBindings;
use PHPUnit\Framework\TestCase;

class ContextualBindingsTest extends TestCase
{
    public function testSetAndGetReturnsStringBinding(): void
    {
        $bindings = new ContextualBindings();

        $bindings->set('ConsumerA', 'DependencyX', 'ConcreteService');

        $this->assertSame('ConcreteService', $bindings->get('ConsumerA', 'DependencyX'));
    }

    public function testSetAndGetReturnsClosureBinding(): void
    {
        $bindings = new ContextualBindings();

        $closure = fn(): string => 'resolved';
        $bindings->set('ConsumerB', 'DependencyY', $closure);

        $this->assertSame($closure, $bindings->get('ConsumerB', 'DependencyY'));
    }

    public function testGetReturnsNullWhenUnset(): void
    {
        $bindings = new ContextualBindings();

        $this->assertNull($bindings->get('Nope', 'Nada'));
    }

    public function testHasReturnsTrueWhenBindingExists(): void
    {
        $bindings = new ContextualBindings();
        $bindings->set('ConsumerC', 'DependencyZ', 'ConcreteZ');

        $this->assertTrue($bindings->has('ConsumerC', 'DependencyZ'));
    }

    public function testHasReturnsFalseWhenBindingDoesNotExist(): void
    {
        $bindings = new ContextualBindings();

        $this->assertFalse($bindings->has('MissingConsumer', 'MissingDependency'));
    }

    public function testOverwritingBindingReplacesValue(): void
    {
        $bindings = new ContextualBindings();

        $bindings->set('ConsumerA', 'DependencyX', 'FirstValue');
        $bindings->set('ConsumerA', 'DependencyX', 'OverwrittenValue');

        $this->assertSame('OverwrittenValue', $bindings->get('ConsumerA', 'DependencyX'));
    }

    public function testMultipleConsumersCanHaveSameDependency(): void
    {
        $bindings = new ContextualBindings();

        $bindings->set('Consumer1', 'SharedDependency', 'Value1');
        $bindings->set('Consumer2', 'SharedDependency', 'Value2');

        $this->assertSame('Value1', $bindings->get('Consumer1', 'SharedDependency'));
        $this->assertSame('Value2', $bindings->get('Consumer2', 'SharedDependency'));
    }

    public function testClosureBindingCanBeExecuted(): void
    {
        $bindings = new ContextualBindings();

        $bindings->set('ConsumerD', 'DependencyW', fn(): string => 'works');

        $closure = $bindings->get('ConsumerD', 'DependencyW');

        $this->assertInstanceOf(Closure::class, $closure);
        $this->assertSame('works', $closure());
    }

    public function testDifferentDependenciesForSameConsumer(): void
    {
        $bindings = new ContextualBindings();

        $bindings->set('ConsumerE', 'Dep1', 'A');
        $bindings->set('ConsumerE', 'Dep2', 'B');

        $this->assertSame('A', $bindings->get('ConsumerE', 'Dep1'));
        $this->assertSame('B', $bindings->get('ConsumerE', 'Dep2'));
    }
}