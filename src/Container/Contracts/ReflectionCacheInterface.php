<?php

declare(strict_types=1);

namespace Maduser\Argon\Container\Contracts;

use Maduser\Argon\Container\Exceptions\ContainerException;
use ReflectionClass;

/**
 * Caches ReflectionClass instances for reuse across the container.
 */
interface ReflectionCacheInterface
{
    /**
     * @param class-string $className
     * @return ReflectionClass<object>
     * @throws ContainerException
     */
    public function get(string $className): ReflectionClass;
}
