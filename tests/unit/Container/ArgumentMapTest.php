<?php

declare(strict_types=1);

namespace Tests\Unit\Container;

use Maduser\Argon\Container\ArgumentMap;
use PHPUnit\Framework\TestCase;

final class ArgumentMapTest extends TestCase
{
    public function testConstructorInitializesParameters(): void
    {
        $initial = [
            'Test\ClassA' => ['param1' => 'value1']
        ];

        $map = new ArgumentMap($initial);

        $this->assertSame($initial, $map->all());
    }

    public function testSetParametersOverwritesAll(): void
    {
        $map = new ArgumentMap([
            'Old\Scope' => ['x' => 1]
        ]);

        $new = [
            'New\Scope' => ['y' => 2]
        ];

        $map->setArguments($new);
        $this->assertSame($new, $map->all());
    }

    public function testSetAddsScopedParams(): void
    {
        $map = new ArgumentMap();
        $params = ['param1' => 'value1', 'param2' => 'value2'];

        $map->set('My\Class', $params);

        $this->assertSame($params, $map->get('My\Class'));
    }

    public function testGetReturnsEmptyArrayWhenScopeMissing(): void
    {
        $map = new ArgumentMap();

        $this->assertSame([], $map->get('Unknown\Scope'));
    }

    public function testHasReturnsTrueWhenParameterExists(): void
    {
        $map = new ArgumentMap(
            ['Scope\One' => ['foo' => 'bar']]
        );

        $this->assertTrue($map->has('Scope\One', 'foo'));
    }

    public function testHasReturnsFalseWhenParameterMissing(): void
    {
        $map = new ArgumentMap(
            ['Scope\One' => ['foo' => 'bar']]
        );

        $this->assertFalse($map->has('Scope\One', 'missing'));
        $this->assertFalse($map->has('Missing\Scope', 'foo'));
    }

    public function testGetScopedReturnsValue(): void
    {
        $map = new ArgumentMap(
            ['Scope\Two' => ['key' => 'value']]
        );

        $this->assertSame('value', $map->getArgument('Scope\Two', 'key'));
    }

    public function testGetScopedReturnsDefaultIfMissing(): void
    {
        $map = new ArgumentMap();

        $this->assertSame('default', $map->getArgument('None', 'nope', 'default'));
    }

    public function testAllReturnsEverything(): void
    {
        $data = [
            'A' => ['a' => 1],
            'B' => ['b' => 2],
        ];

        $map = new ArgumentMap($data);

        $this->assertSame($data, $map->all());
    }

    public function testSetOverwritesPreviousScope(): void
    {
        $map = new ArgumentMap(
            ['Scope\Two' => ['key' => 'value']]
        );

        $map->set('ScopeX', ['new' => 'val2']);

        $this->assertSame(['new' => 'val2'], $map->get('ScopeX'));
        $this->assertFalse($map->has('ScopeX', 'old'));
    }
}
