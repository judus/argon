<?php

declare(strict_types=1);

namespace Tests\Unit\Container;

use Maduser\Argon\Container\ParameterStore;
use PHPUnit\Framework\TestCase;

final class ParameterStoreTest extends TestCase
{
    public function testSetAndGet(): void
    {
        $store = new ParameterStore();
        $store->set('foo', 'bar');

        $this->assertSame('bar', $store->get('foo'));
    }

    public function testGetReturnsDefaultIfKeyMissing(): void
    {
        $store = new ParameterStore();

        $this->assertSame('default', $store->get('missing', 'default'));
    }

    public function testHasReturnsCorrectly(): void
    {
        $store = new ParameterStore();

        $this->assertFalse($store->has('nope'));
        $store->set('exists', 123);
        $this->assertTrue($store->has('exists'));
    }

    public function testAllReturnsAllParameters(): void
    {
        $store = new ParameterStore();
        $store->set('a', 1);
        $store->set('b', 2);

        $this->assertSame(['a' => 1, 'b' => 2], $store->all());
    }
}
