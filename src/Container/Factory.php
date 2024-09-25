<?php

declare(strict_types=1);

namespace Maduser\Argon\Container;

use Exception;
use ReflectionClass;
use ReflectionException;
use ReflectionNamedType;

/**
 * Class Factory
 *
 * Responsible for creating instances of classes, handling dependency injection via reflection.
 *
 * @package Maduser\Minimal\Provider
 */
class Factory
{
    /**
     * @var Provider The service provider that manages bindings, singletons, and providers
     */
    private Provider $provider;

    /**
     * Cache for reflection classes to improve performance.
     *
     * @var array
     */
    private array $reflectionCache = [];

    /**
     * Factory constructor.
     *
     * @param Provider $provider The service provider instance
     */
    public function __construct(Provider $provider)
    {
        $this->provider = $provider;
    }

    /**
     * Creates a new instance of the given class, resolving and injecting dependencies as needed.
     *
     * @param string     $class  The class to instantiate
     * @param array|null $params Optional parameters for instantiation
     *
     * @return object The instantiated class with its dependencies injected
     * @throws ReflectionException If the class cannot be reflected
     * @throws Exception If dependencies cannot be resolved
     */
    public function make(string $class, ?array $params = []): object
    {
        try {
            // Get the reflection class from the cache or create a new one
            $reflectionClass = $this->getReflectionClass($class);

            // If no constructor exists, instantiate the class without arguments
            if (!$constructor = $this->getConstructor($reflectionClass)) {
                return $reflectionClass->newInstance();
            }

            // Resolve the dependencies for the constructor
            $dependencies = $this->resolveDependencies($constructor->getParameters(), $params);

            // Instantiate the class with the resolved dependencies
            return $reflectionClass->newInstanceArgs($dependencies);

        } catch (ReflectionException $e) {
            throw new Exception("Failed to instantiate class '$class': " . $e->getMessage());
        }
    }

    /**
     * Retrieve a ReflectionClass from the cache or create one.
     *
     * @param string $class The class name
     *
     * @return ReflectionClass The reflection of the class
     * @throws ReflectionException If the class cannot be reflected
     */
    private function getReflectionClass(string $class): ReflectionClass
    {
        if (!isset($this->reflectionCache[$class])) {
            $this->reflectionCache[$class] = new ReflectionClass($class);
        }

        return $this->reflectionCache[$class];
    }

    /**
     * Retrieve the constructor of a class.
     *
     * @param ReflectionClass $reflectionClass The reflection of the class
     *
     * @return \ReflectionMethod|null The constructor method or null if none exists
     */
    private function getConstructor(ReflectionClass $reflectionClass): ?\ReflectionMethod
    {
        return $reflectionClass->getConstructor();
    }

    /**
     * Resolve dependencies for a constructor's parameters.
     *
     * @param array      $parameters The constructor parameters
     * @param array|null $params     The provided parameters
     *
     * @return array Resolved dependencies
     * @throws Exception If dependencies cannot be resolved
     */
    private function resolveDependencies(array $parameters, ?array $params): array
    {
        $dependencies = [];

        foreach ($parameters as $parameter) {
            $paramType = $parameter->getType();

            if ($paramType instanceof ReflectionNamedType && !$paramType->isBuiltin()) {
                // Resolve class dependency
                $dependencies[] = $this->provider->resolve($paramType->getName());
            } elseif ($parameter->isOptional()) {
                // Use default parameter value if available
                $dependencies[] = $parameter->getDefaultValue();
            } else {
                // Handle named non-class parameters or use array order fallback
                $paramName = $parameter->getName();
                $dependencies[] = $params[$paramName] ?? array_shift($params);
            }
        }

        return $dependencies;
    }
}
