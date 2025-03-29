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

        $bindings->bind('ConsumerA', 'DependencyX', 'ConcreteService');

        $this->assertSame('ConcreteService', $bindings->get('ConsumerA', 'DependencyX'));
    }

    public function testGetBindingsReturnsAllSetBindingsBecauseItsNotAsObviousAsYouThink(): void
    {
        $bindings = new ContextualBindings();

        $bindings->bind('ConsumerA', 'DepA', 'ConcreteA');
        $bindings->bind('ConsumerA', 'DepB', 'ConcreteB');
        $bindings->bind('ConsumerB', 'DepC', fn() => 'ClosureResult');

        $result = $bindings->getBindings();

        $this->assertCount(2, $result);
        $this->assertArrayHasKey('ConsumerA', $result);
        $this->assertArrayHasKey('ConsumerB', $result);
        $this->assertSame('ConcreteA', $result['ConsumerA']['DepA']);
        $this->assertSame('ConcreteB', $result['ConsumerA']['DepB']);
        $this->assertInstanceOf(Closure::class, $result['ConsumerB']['DepC']);
    }

    public function testSetAndGetReturnsClosureBinding(): void
    {
        $bindings = new ContextualBindings();

        $closure = fn(): string => 'resolved';
        $bindings->bind('ConsumerB', 'DependencyY', $closure);

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
        $bindings->bind('ConsumerC', 'DependencyZ', 'ConcreteZ');

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

        $bindings->bind('ConsumerA', 'DependencyX', 'FirstValue');
        $bindings->bind('ConsumerA', 'DependencyX', 'OverwrittenValue');

        $this->assertSame('OverwrittenValue', $bindings->get('ConsumerA', 'DependencyX'));
    }

    public function testMultipleConsumersCanHaveSameDependency(): void
    {
        $bindings = new ContextualBindings();

        $bindings->bind('Consumer1', 'SharedDependency', 'Value1');
        $bindings->bind('Consumer2', 'SharedDependency', 'Value2');

        $this->assertSame('Value1', $bindings->get('Consumer1', 'SharedDependency'));
        $this->assertSame('Value2', $bindings->get('Consumer2', 'SharedDependency'));
    }

    public function testClosureBindingCanBeExecuted(): void
    {
        $bindings = new ContextualBindings();

        $bindings->bind('ConsumerD', 'DependencyW', fn(): string => 'works');

        $closure = $bindings->get('ConsumerD', 'DependencyW');

        $this->assertInstanceOf(Closure::class, $closure);
        $this->assertSame('works', $closure());
    }

    public function testDifferentDependenciesForSameConsumer(): void
    {
        $bindings = new ContextualBindings();

        $bindings->bind('ConsumerE', 'Dep1', 'A');
        $bindings->bind('ConsumerE', 'Dep2', 'B');

        $this->assertSame('A', $bindings->get('ConsumerE', 'Dep1'));
        $this->assertSame('B', $bindings->get('ConsumerE', 'Dep2'));
    }
}
