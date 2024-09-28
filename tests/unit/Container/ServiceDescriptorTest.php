<?php

namespace Tests\Unit\Container;

use Maduser\Argon\Container\ServiceDescriptor;
use Maduser\Argon\Container\ServiceContainer;
use PHPUnit\Framework\TestCase;

class ServiceDescriptorTest extends TestCase
{
    public function testSetAndGetResolvedInstance()
    {
        $descriptor = new ServiceDescriptor('stdClass', \stdClass::class);
        $object = new \stdClass();

        $descriptor->setResolvedInstance($object);
        $this->assertSame($object, $descriptor->getResolvedInstance());
    }

    public function testIsSingleton()
    {
        $descriptor = new ServiceDescriptor('stdClass', \stdClass::class, true);
        $this->assertTrue($descriptor->isSingleton());

        $descriptor = new ServiceDescriptor('stdClass', \stdClass::class, false);
        $this->assertFalse($descriptor->isSingleton());
    }

    public function testGetClassName()
    {
        $descriptor = new ServiceDescriptor('stdClass', \stdClass::class);
        $this->assertEquals(\stdClass::class, $descriptor->getClassName());
    }
}