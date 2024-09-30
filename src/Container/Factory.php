<?php
declare(strict_types=1);

namespace Maduser\Argon\Container;

use Maduser\Argon\Container\Exceptions\ContainerException;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;

/**
 * Class Factory
 *
 * Responsible for creating instances of classes, handling dependency injection via reflection.
 *
 * @package Maduser\Argon\Container
 */
class Factory
{
    /**
     * @var ServiceContainer The service container that manages bindings, singletons, and providers
     */
    private ServiceContainer $container;

    /**
     * Cache for reflection classes to improve performance.
     *
     * @var array<string, ReflectionClass>
     */
    private array $reflectionCache = [];

    /**
     * Factory constructor.
     *
     * @param ServiceContainer $container
     */
    public function __construct(ServiceContainer $container)
    {
        $this->container = $container;
    }

    /**
     * Creates a new instance of the given class, resolving and injecting dependencies as needed.
     *
     * @param string     $class  The class to instantiate
     * @param array|null $params Optional parameters for instantiation
     *
     * @return object The instantiated class with its dependencies injected
     * @throws ContainerErrorException If instantiation fails
     */
    public function make(string $class, ?array $params = []): object
    {
        try {
            // Return the container itself if the class is the ServiceContainer
            if ($class === ServiceContainer::class) {
                return $this->container;
            }

            // Check if the class is an interface and if there's a binding for it
            if (interface_exists($class) && $this->container->bindings()->has($class)) {
                $concreteClass = $this->container->bindings()->get($class);

                // Resolve the bound concrete class
                return $this->container->resolveOrMake($concreteClass, $params);
            }

            // Get or cache the reflection class
            $reflectionClass = $this->getReflectionClass($class);

            // Instantiate without arguments if no constructor is found
            if (!$constructor = $this->getConstructor($reflectionClass)) {
                return $reflectionClass->newInstance();
            }

            // Resolve the dependencies for the constructor
            $dependencies = $this->resolveDependencies($constructor->getParameters(), $params);

            // Instantiate the class with the resolved dependencies
            return $reflectionClass->newInstanceArgs($dependencies);
        } catch (ReflectionException $e) {
            throw new ContainerException("Failed to instantiate class '$class': " . $e->getMessage(), $e);
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
     * @return ReflectionMethod|null The constructor method or null if none exists
     */
    private function getConstructor(ReflectionClass $reflectionClass): ?ReflectionMethod
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
     * @throws ContainerErrorException If dependencies cannot be resolved
     */
    public function resolveDependencies(array $parameters, ?array $params): array
    {
        $dependencies = [];

        foreach ($parameters as $parameter) {
            // Resolve dependencies based on the parameter type
            $dependencies[] = $this->resolveParameter($parameter, $params);
        }

        return $dependencies;
    }

    private function resolveParameter(ReflectionParameter $parameter, ?array $params): mixed
    {
        $paramType = $parameter->getType();

        if ($paramType instanceof ReflectionNamedType && !$paramType->isBuiltin()) {
            return $this->resolveClassParameter($paramType->getName());
        }

        if ($parameter->isOptional()) {
            return $parameter->getDefaultValue();
        }

        return $this->resolvePositionalParameter($parameter->getName(), $params);
    }

    private function resolveClassParameter(string $className): mixed
    {
        // Check if the parameter type is the ServiceContainer itself
        if ($className === ServiceContainer::class) {
            return $this->container;
        }

        // Check if there is a binding for interfaces to concrete classes
        if ($this->container->bindings()->has($className)) {

            $concreteClass = $this->container->bindings()->get($className);

            return $this->container->resolveOrMake($concreteClass);
        }

        // Resolve or make an instance of the class
        return $this->container->resolveOrMake($className);
    }

    private function resolvePositionalParameter(string $paramName, ?array $params): mixed
    {
        return $params[$paramName] ?? array_shift($params);
    }
}
