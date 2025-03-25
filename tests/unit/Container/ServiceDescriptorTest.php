<?php

declare(strict_types=1);

namespace Tests\Unit\Container;

use Maduser\Argon\Container\ServiceDescriptor;
use PHPUnit\Framework\TestCase;
use stdClass;

class ServiceDescriptorTest extends TestCase
{
    public function testIsSingletonReturnsTrueWhenSet(): void
    {
        $descriptor = new ServiceDescriptor(stdClass::class, true);

        $this->assertTrue($descriptor->isSingleton());
    }

    public function testIsSingletonReturnsFalseWhenNotSet(): void
    {
        $descriptor = new ServiceDescriptor(stdClass::class, false);

        $this->assertFalse($descriptor->isSingleton());
    }

    public function testGetConcreteReturnsClassString(): void
    {
        $descriptor = new ServiceDescriptor(stdClass::class, true);

        $this->assertSame(stdClass::class, $descriptor->getConcrete());
    }

    public function testGetConcreteReturnsClosure(): void
    {
        $closure = fn(): object => new stdClass();
        $descriptor = new ServiceDescriptor($closure, true);

        $this->assertSame($closure, $descriptor->getConcrete());
    }

    public function testGetInstanceReturnsNullInitially(): void
    {
        $descriptor = new ServiceDescriptor(stdClass::class, true);

        $this->assertNull($descriptor->getInstance());
    }

    public function testStoreInstanceOnlyStoresOnceIfSingleton(): void
    {
        $descriptor = new ServiceDescriptor(stdClass::class, true);

        $instance1 = new stdClass();
        $instance2 = new stdClass();

        $descriptor->storeInstance($instance1);
        $descriptor->storeInstance($instance2); // should be ignored

        $this->assertSame($instance1, $descriptor->getInstance());
    }

    public function testStoreInstanceHasNoEffectIfNotSingleton(): void
    {
        $descriptor = new ServiceDescriptor(stdClass::class, false);

        $descriptor->storeInstance(new stdClass());

        $this->assertNull($descriptor->getInstance(), 'Transient services should not store instance.');
    }
}
