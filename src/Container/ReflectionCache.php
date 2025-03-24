<?php

declare(strict_types=1);

namespace Maduser\Argon\Container;

use ReflectionClass;
use ReflectionException;

class ReflectionCache
{
    /**
     * @var array<string, ReflectionClass>
     */
    private array $reflectionCache = [];

    /**
     * Retrieves the ReflectionClass instance for a given class name.
     * Caches the ReflectionClass for future resolutions.
     *
     * @param string $className The class name.
     * @return ReflectionClass The cached or newly created ReflectionClass.
     * @throws ReflectionException
     */
    public function get(string $className): ReflectionClass
    {
        if (!isset($this->reflectionCache[$className])) {
            if (!class_exists($className) && !interface_exists($className) && !trait_exists($className)) {
                throw new ReflectionException("Class, interface, or trait '$className' does not exist");
            }

            $this->reflectionCache[$className] = new ReflectionClass($className);
        }

        return $this->reflectionCache[$className];
    }
}
