<?php

declare(strict_types=1);

namespace Tests\Unit\Container\Support;

use Maduser\Argon\Container\Support\CallableWrapper;
use PHPUnit\Framework\TestCase;
use ReflectionException;
use ReflectionFunction;
use ReflectionMethod;

class CallableWrapperTest extends TestCase
{
    /**
     * @throws ReflectionException
     */
    public function testWrapperStoresInstanceAndReflection(): void
    {
        $callable = fn(): string => 'hi';
        $reflection = new ReflectionFunction($callable);

        $wrapper = new CallableWrapper(null, $reflection);

        $this->assertNull($wrapper->getInstance());
        $this->assertSame($reflection, $wrapper->getReflection());
    }

    /**
     * @throws ReflectionException
     */
    public function testWrapperStoresInstanceReference(): void
    {
        $object = new class {
            public function method(): void
            {
            }
        };

        $reflection = new ReflectionMethod($object, 'method');
        $wrapper = new CallableWrapper($object, $reflection);

        $this->assertSame($object, $wrapper->getInstance());
        $this->assertSame($reflection, $wrapper->getReflection());
    }
}
