<?php

declare(strict_types=1);

namespace Tests\Unit\Container;

use Maduser\Argon\Container\ParameterRegistry;
use PHPUnit\Framework\TestCase;

class ParameterRegistryTest extends TestCase
{
    public function testConstructorInitializesParameters(): void
    {
        $initial = [
            'Test\ClassA' => ['param1' => 'value1']
        ];

        $registry = new ParameterRegistry($initial);

        $this->assertSame($initial, $registry->all());
    }

    public function testSetParametersOverwritesAll(): void
    {
        $registry = new ParameterRegistry([
            'Old\Scope' => ['x' => 1]
        ]);

        $new = [
            'New\Scope' => ['y' => 2]
        ];

        $registry->setParameters($new);
        $this->assertSame($new, $registry->all());
    }

    public function testSetAddsScopedParams(): void
    {
        $registry = new ParameterRegistry();
        $params = ['param1' => 'value1', 'param2' => 'value2'];

        $registry->set('My\Class', $params);

        $this->assertSame($params, $registry->get('My\Class'));
    }

    public function testGetReturnsEmptyArrayWhenScopeMissing(): void
    {
        $registry = new ParameterRegistry();

        $this->assertSame([], $registry->get('Unknown\Scope'));
    }

    public function testHasReturnsTrueWhenParameterExists(): void
    {
        $registry = new ParameterRegistry([
            'Scope\One' => ['foo' => 'bar']
        ]);

        $this->assertTrue($registry->has('Scope\One', 'foo'));
    }

    public function testHasReturnsFalseWhenParameterMissing(): void
    {
        $registry = new ParameterRegistry([
            'Scope\One' => ['foo' => 'bar']
        ]);

        $this->assertFalse($registry->has('Scope\One', 'missing'));
        $this->assertFalse($registry->has('Missing\Scope', 'foo'));
    }

    public function testGetScopedReturnsValue(): void
    {
        $registry = new ParameterRegistry([
            'Scope\Two' => ['key' => 'value']
        ]);

        $this->assertSame('value', $registry->getScoped('Scope\Two', 'key'));
    }

    public function testGetScopedReturnsDefaultIfMissing(): void
    {
        $registry = new ParameterRegistry();

        $this->assertSame('default', $registry->getScoped('None', 'nope', 'default'));
    }

    public function testAllReturnsEverything(): void
    {
        $data = [
            'A' => ['a' => 1],
            'B' => ['b' => 2],
        ];

        $registry = new ParameterRegistry($data);

        $this->assertSame($data, $registry->all());
    }

    public function testSetOverwritesPreviousScope(): void
    {
        $registry = new ParameterRegistry([
            'ScopeX' => ['old' => 'val']
        ]);

        $registry->set('ScopeX', ['new' => 'val2']);

        $this->assertSame(['new' => 'val2'], $registry->get('ScopeX'));
        $this->assertFalse($registry->has('ScopeX', 'old'));
    }
}
