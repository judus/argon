<?php

declare(strict_types=1);

namespace Tests\Unit\Container;

use Maduser\Argon\Container\ReflectionCache;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionException;
use stdClass;

class ReflectionCacheTest extends TestCase
{
    /**
     * @throws ReflectionException
     */
    public function testReturnsReflectionInstance(): void
    {
        $cache = new ReflectionCache();
        $reflection = $cache->get(stdClass::class);

        $this->assertInstanceOf(ReflectionClass::class, $reflection);
        $this->assertSame(stdClass::class, $reflection->getName());
    }

    /**
     * @throws ReflectionException
     */
    public function testCachesReflectionInstance(): void
    {
        $cache = new ReflectionCache();

        $first = $cache->get(stdClass::class);
        $second = $cache->get(stdClass::class);

        $this->assertSame($first, $second, 'Reflection instance should be cached and reused.');
    }

    public function testThrowsExceptionForNonExistentClass(): void
    {
        $this->expectException(ReflectionException::class);

        $cache = new ReflectionCache();
        $cache->get('This\\Class\\Does\\Not\\Exist');
    }
}
