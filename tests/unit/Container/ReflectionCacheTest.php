<?php

declare(strict_types=1);

namespace Tests\Unit\Container;

use Maduser\Argon\Container\Exceptions\ContainerException;
use Maduser\Argon\Container\ReflectionCache;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionException;
use stdClass;
use Tests\Unit\Container\Mocks\SampleInterface;
use Tests\Unit\Container\Mocks\SampleTrait;

class ReflectionCacheTest extends TestCase
{
    /**
     * @throws ContainerException
     */
    public function testReturnsReflectionInstance(): void
    {
        $cache = new ReflectionCache();
        $reflection = $cache->get(stdClass::class);

        $this->assertInstanceOf(ReflectionClass::class, $reflection);
        $this->assertSame(stdClass::class, $reflection->getName());
    }

    /**
     * @throws ContainerException
     */
    public function testCachesReflectionInstance(): void
    {
        $cache = new ReflectionCache();

        $first = $cache->get(stdClass::class);
        $second = $cache->get(stdClass::class);

        $this->assertSame($first, $second, 'Reflection instance should be cached and reused.');
    }

    /**
     * @psalm-suppress ArgumentTypeCoercion
     * @psalm-suppress UndefinedClass
     */
    public function testThrowsExceptionForNonExistentClass(): void
    {
        $this->expectException(ContainerException::class);

        $cache = new ReflectionCache();
        $cache->get('NonExistentClass');
    }

    /**
     * @throws ContainerException
     */
    public function testReflectionWorksForInterface(): void
    {
        $cache = new ReflectionCache();
        $reflection = $cache->get(SampleInterface::class);

        $this->assertTrue($reflection->isInterface());
    }

    /**
     * @throws ContainerException
     */
    public function testReflectionWorksForTrait(): void
    {
        $cache = new ReflectionCache();
        $reflection = $cache->get(SampleTrait::class);

        $this->assertTrue($reflection->isTrait());
    }
}
