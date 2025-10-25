<?php

declare(strict_types=1);

namespace Maduser\Argon\Container;

use Maduser\Argon\Container\Contracts\ReflectionCacheInterface;
use Maduser\Argon\Container\Exceptions\ContainerException;
use ReflectionClass;

/**
 * Caches ReflectionClass instances for reuse across the container.
 */
final class ReflectionCache implements ReflectionCacheInterface
{
    /**
     * @var array<class-string, ReflectionClass<object>>
     */
    private array $reflectionCache = [];

    /**
     * @param class-string $className
     * @return ReflectionClass<object>
     * @throws ContainerException
     */
    #[\Override]
    public function get(string $className): ReflectionClass
    {
        if (!isset($this->reflectionCache[$className])) {
            if (!class_exists($className) && !interface_exists($className) && !trait_exists($className)) {
                throw new ContainerException("Class, interface, or trait '$className' does not exist.");
            }

            $reflection = new ReflectionClass($className);
            $this->reflectionCache[$className] = $reflection;
        }

        return $this->reflectionCache[$className];
    }
}
