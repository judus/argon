<?php

namespace Tests\Unit\Container;

use Maduser\Argon\Container\Registry;
use PHPUnit\Framework\TestCase;

class RegistryTest extends TestCase
{
    public function testArrayAccessSetAndGet(): void
    {
        $registry = new Registry();
        $registry['foo'] = 'bar';

        $this->assertTrue(isset($registry['foo']));
        $this->assertEquals('bar', $registry['foo']);
    }

    public function testArrayAccessUnset(): void
    {
        $registry = new Registry();
        $registry['foo'] = 'bar';

        unset($registry['foo']);
        $this->assertFalse(isset($registry['foo']));
    }

    public function testArrayAccessGetNonExistentKey(): void
    {
        $registry = new Registry();

        $this->assertNull($registry['foo']);
    }

    public function testIterator(): void
    {
        $registry = new Registry(['one' => 1, 'two' => 2, 'three' => 3]);

        $expectedKeys = ['one', 'two', 'three'];
        $expectedValues = [1, 2, 3];
        $keys = [];
        $values = [];

        foreach ($registry as $key => $value) {
            $keys[] = $key;
            $values[] = $value;
        }

        $this->assertEquals($expectedKeys, $keys);
        $this->assertEquals($expectedValues, $values);
    }

    public function testAddAndGet(): void
    {
        $registry = new Registry();
        $registry->add('key1', 'value1');

        $this->assertEquals('value1', $registry->get('key1'));
        $this->assertNull($registry->get('non-existent-key'));
    }

    public function testHas(): void
    {
        $registry = new Registry();
        $registry->add('key1', 'value1');

        $this->assertTrue($registry->has('key1'));
        $this->assertFalse($registry->has('key2'));
    }

    public function testSetAndAll(): void
    {
        $items = ['key1' => 'value1', 'key2' => 'value2'];
        $registry = new Registry();
        $registry->set($items);

        $this->assertEquals($items, $registry->all());
    }

    public function testEach(): void
    {
        $registry = new Registry(['one' => 1, 'two' => 2, 'three' => 3]);
        $newRegistry = $registry->each(function ($item) {
            return $item * 2;
        });

        // Preserve the keys
        $this->assertEquals(['one' => 1, 'two' => 2, 'three' => 3], $registry->all());
        $this->assertEquals(['one' => 2, 'two' => 4, 'three' => 6], $newRegistry->all());
    }

    public function testRewind(): void
    {
        $registry = new Registry(['one' => 1, 'two' => 2, 'three' => 3]);
        $registry->rewind();
        $this->assertEquals(1, $registry->current());
    }

    public function testCurrentNextKeyValid(): void
    {
        $registry = new Registry(['one' => 1, 'two' => 2, 'three' => 3]);

        $this->assertEquals(1, $registry->current());
        $this->assertEquals('one', $registry->key());
        $this->assertTrue($registry->valid());

        $registry->next();
        $this->assertEquals(2, $registry->current());
        $this->assertEquals('two', $registry->key());
        $this->assertTrue($registry->valid());

        $registry->next();
        $this->assertEquals(3, $registry->current());
        $this->assertEquals('three', $registry->key());
        $this->assertTrue($registry->valid());

        $registry->next();
        $this->assertFalse($registry->valid());
    }
}
