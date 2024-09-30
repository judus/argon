<?php

namespace Tests\Unit\Container;

use Maduser\Argon\Container\ServiceDescriptor;
use PHPUnit\Framework\TestCase;
use stdClass;

class ServiceDescriptorTest extends TestCase
{
    public function testSetAndGetResolvedInstance(): void
    {
        $descriptor = new ServiceDescriptor('stdClass', stdClass::class);
        $object = new stdClass();

        $descriptor->setInstance($object);
        $this->assertSame($object, $descriptor->getInstance());
    }

    public function testIsSingleton(): void
    {
        $descriptor = new ServiceDescriptor('stdClass', stdClass::class, true);
        $this->assertTrue($descriptor->isSingleton());

        $descriptor = new ServiceDescriptor('stdClass', stdClass::class, false);
        $this->assertFalse($descriptor->isSingleton());
    }

    public function testGetClassName(): void
    {
        $descriptor = new ServiceDescriptor('stdClass', stdClass::class);
        $this->assertEquals(stdClass::class, $descriptor->getClassName());
    }
}
