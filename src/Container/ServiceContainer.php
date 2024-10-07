<?php

declare(strict_types=1);

namespace Maduser\Argon\Container;

use Closure;
use Maduser\Argon\Container\Contracts\ServiceProviderInterface;
use Maduser\Argon\Container\Contracts\TypeInterceptorInterface;
use Maduser\Argon\Container\Exceptions\CircularDependencyException;
use Maduser\Argon\Container\Exceptions\ContainerException;
use Maduser\Argon\Container\Exceptions\NotFoundException;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use ReflectionException;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionParameter;

/**
 * Class ServiceContainer
 *
 * A PSR-11 compliant service container for managing and resolving dependencies.
 * It supports service providers, type-specific interceptors, parameter overrides,
 * circular dependency detection, and singleton or transient service binding.
 */
class ServiceContainer implements ContainerInterface
{
    /**
     * @var array Holds the registered services in the container.
     */
    private array $services = [];

    /**
     * @var array Stores type-based interceptors.
     */
    private array $typeInterceptors = [];

    /**
     * @var array Holds the registered service providers.
     */
    private array $serviceProviders = [];

    /**
     * @var array Tracks services currently being resolved to detect circular dependencies.
     */
    private array $resolving = [];

    /**
     * @var array Caches ReflectionClass instances for faster lookups during service resolution.
     */
    private array $reflectionCache = [];

    /**
     * @var ParameterOverrideRegistry Registry that manages global parameter overrides.
     */
    private ParameterOverrideRegistry $overrideRegistry;

    /**
     * Constructor for ServiceContainer.
     *
     * @param ParameterOverrideRegistry $overrideRegistry Registry for managing parameter overrides.
     */
    public function __construct(
        ParameterOverrideRegistry $overrideRegistry = new ParameterOverrideRegistry()
    ) {
        $this->overrideRegistry = $overrideRegistry;
    }

    /**
     * Registers a service provider and triggers the registration method.
     *
     * @param ServiceProviderInterface $provider The service provider instance.
     * @return void
     */
    public function registerServiceProvider(ServiceProviderInterface $provider): void
    {
        $provider->register($this);
        $this->serviceProviders[] = $provider;
    }

    /**
     * Boots all registered service providers by calling their boot methods.
     *
     * @return void
     */
    public function bootServiceProviders(): void
    {
        foreach ($this->serviceProviders as $provider) {
            $provider->boot($this);
        }
    }

    /**
     * Registers a type-based interceptor.
     *
     * @param TypeInterceptorInterface $interceptor The type-based interceptor instance.
     * @return void
     */
    public function registerTypeInterceptor(TypeInterceptorInterface $interceptor): void
    {
        $this->typeInterceptors[] = $interceptor;
    }

    /**
     * Registers a singleton service in the container.
     *
     * @param string $id The service identifier.
     * @param Closure|string $concrete The concrete class or closure to instantiate.
     * @return void
     */
    public function singleton(string $id, Closure|string $concrete): void
    {
        $this->bind($id, $concrete, true);
    }

    /**
     * Binds a service (transient or singleton) to the container.
     *
     * @param string $id The service identifier.
     * @param Closure|string $concrete The concrete class or closure to instantiate.
     * @param bool $isSingleton If true, the service will be treated as a singleton.
     * @throws ContainerException If the class does not exist.
     * @return void
     */
    public function bind(string $id, Closure|string $concrete, bool $isSingleton = false): void
    {
        if ($id === $concrete) {
            throw ContainerException::fromServiceId($id, "A class cannot be bound to itself.");
        }

        if (is_string($concrete) && !class_exists($concrete)) {
            throw ContainerException::fromServiceId($id, "Class '$concrete' does not exist.");
        }

        $this->services[$id] = new ServiceDescriptor($concrete, $isSingleton);
    }

    /**
     * Registers a service factory method, which may optionally be a singleton.
     *
     * @param string $id The service identifier.
     * @param callable $factory A callable that acts as a factory for the service.
     * @param bool $isSingleton If true, the factory will produce singleton instances.
     * @return void
     */
    public function registerFactory(string $id, callable $factory, bool $isSingleton = true): void
    {
        $this->services[$id] = new ServiceDescriptor($factory, $isSingleton);
    }

    /**
     * Determines whether the container has a service by its identifier.
     *
     * @param string $id The service identifier.
     *
     * @return bool True if the service is registered in the container, false otherwise.
     */
    public function has(string $id): bool
    {
        return isset($this->services[$id]) || class_exists($id);
    }

    /**
     * Tags a service with one or more tags.
     *
     * @param string $id   The service identifier
     * @param array  $tags The tags to associate with the service
     */
    public function tag(string $id, array $tags): void
    {
        foreach ($tags as $tag) {
            $this->tags[$tag][] = $id;
        }
    }

    /**
     * Retrieves all services associated with a tag.
     *
     * @param string $tag The tag to search for
     *
     * @return array The services associated with the given tag
     */
    public function getTaggedServices(string $tag): array
    {
        $taggedServices = [];

        if (isset($this->tags[$tag])) {
            foreach ($this->tags[$tag] as $serviceId) {
                $taggedServices[] = $this->get($serviceId);
            }
        }

        return $taggedServices;
    }

    /**
     * Resolves and retrieves a service by its identifier.
     *
     * @param string $id The service identifier.
     * @return object The resolved service.
     * @throws NotFoundException If the service is not found.
     */
    public function get(string $id): object
    {
        return $this->resolveService($id);
    }

    /**
     * Resolves a service by its identifier, optionally applying overrides.
     *
     * @param string $id        The service identifier.
     * @param array  $overrides Optional parameter overrides.
     *
     * @return object The resolved service instance.
     * @throws ContainerException If the service is not found.
     */
    private function resolveService(string $id, array $overrides = []): object
    {
        $this->checkCircularDependency($id);

        // Check if the service is registered or attempt to resolve it by class
        if (!isset($this->services[$id])) {
            if (class_exists($id)) {
                $reflection = $this->getReflection($id);

                if (!$reflection->isInstantiable()) {
                    throw ContainerException::forNonInstantiableClass($id, $reflection->getName());
                }

                return $this->resolveClass($id, $overrides);
            }

            throw NotFoundException::forService($id);
        }

        // Retrieve service descriptor and handle normal resolution logic
        $descriptor = $this->services[$id];
        $instance = $descriptor->getInstance();
        $concrete = $descriptor->getConcrete();

        // Return existing singleton instance if already resolved
        if ($descriptor->isSingleton() && $instance !== null) {
            $this->removeFromResolving($id);

            return $instance;
        }

        // If the concrete is a closure, invoke it; otherwise, resolve the class
        if ($concrete instanceof Closure) {
            $instance = $concrete();
        } else {
            $instance = $this->resolveClass($concrete, $overrides);
        }

        // Apply type-specific interceptors
        $instance = $this->applyTypeInterceptors($instance);

        // Store the singleton instance if it's a singleton
        if ($descriptor->isSingleton()) {
            $descriptor->storeInstance($instance);
        }

        $this->removeFromResolving($id);

        return $instance;
    }


    /**
     * Checks for circular dependencies while resolving services.
     *
     * @param string $id The service identifier being resolved.
     * @throws ContainerException If a circular dependency is detected.
     */
    private function checkCircularDependency(string $id): void
    {
        if (isset($this->resolving[$id])) {
            // Add the starting service to the end of the chain
            $chain = array_keys($this->resolving);
            $chain[] = $id;

            throw ContainerException::forCircularDependency($id, $chain);
        }
        $this->resolving[$id] = true;
    }

    /**
     * Retrieves the ReflectionClass instance for a given class name.
     * Caches the ReflectionClass for future resolutions.
     *
     * @param string $className The class name.
     * @return ReflectionClass The cached or newly created ReflectionClass.
     */
    private function getReflection(string $className): ReflectionClass
    {
        return $this->reflectionCache[$className] ??= new ReflectionClass($className);
    }

    /**
     * Resolves a class and its dependencies, using optional overrides.
     *
     * @param string $className The class name to resolve.
     * @param array $overrides Optional parameter overrides.
     * @return object The instantiated class.
     * @throws ContainerException If the class cannot be instantiated.
     */
    private function resolveClass(string $className, array $overrides = []): object
    {
        // Check if the className is bound to a concrete class
        if (isset($this->services[$className])) {
            $descriptor = $this->services[$className];
            $concrete = $descriptor->getConcrete();

            // If the concrete is a closure, invoke it and return
            return $concrete instanceof Closure
                ? $concrete()
                : $this->resolveClass($concrete, $overrides);
        }

        $reflection = $this->getReflection($className);

        // Check if the class is an interface and throw an exception if it's not bound
        if ($reflection->isInterface()) {
            throw ContainerException::forUnresolvableDependency($className, "Cannot instantiate interface");
        }

        if (!$reflection->isInstantiable()) {
            throw ContainerException::forNonInstantiableClass($className, $reflection->getName());
        }

        $constructor = $reflection->getConstructor();

        // If there's no constructor, just instantiate the class
        if (is_null($constructor)) {
            return new $className();
        }

        // Resolve constructor parameters
        $dependencies = array_map(
            fn(ReflectionParameter $param) => $this->resolveParameterWithOverrides($param, $overrides),
            $constructor->getParameters()
        );

        return $reflection->newInstanceArgs($dependencies);
    }

    /**
     * Resolves a parameter using global and specific overrides.
     *
     * @param ReflectionParameter $param The reflection parameter to resolve.
     * @param array $overrides Optional parameter overrides.
     * @return mixed The resolved parameter value.
     */
    private function resolveParameterWithOverrides(ReflectionParameter $param, array $overrides = []): mixed
    {
        $className = $param->getDeclaringClass() ? $param->getDeclaringClass()->getName() : 'unknown class';
        $paramName = $param->getName();

        // Check for an override (manual binding)
        $mergedOverrides = array_merge(
            $this->overrideRegistry->getOverridesForClass($className),
            $overrides
        );

        if (isset($mergedOverrides[$paramName])) {
            return $mergedOverrides[$paramName];
        }

        $paramType = $param->getType();

        // If the parameter has no type hint (non-class primitive), check for a default value
        if ($paramType === null || $paramType->isBuiltin()) {
            // Handle default values for optional parameters
            if ($param->isOptional()) {
                return $param->getDefaultValue();
            }

            // If it's a primitive type with no override or default value
            throw ContainerException::forUnresolvedPrimitive($className, $paramName);
        }

        // If the parameter has a class type hint, resolve from the container
        return $this->get($paramType->getName());
    }

    /**
     * Removes the service identifier from the resolving stack after resolution.
     *
     * @param string $id The service identifier.
     */
    private function removeFromResolving(string $id): void
    {
        unset($this->resolving[$id]);
    }

    /**
     * Apply registered type-specific interceptors to the resolved instance.
     *
     * @param object $instance The resolved service instance.
     * @return object The intercepted instance.
     */
    private function applyTypeInterceptors(object $instance): object
    {
        foreach ($this->typeInterceptors as $interceptor) {
            if ($interceptor->supports($instance)) {
                $interceptor->intercept($instance);
            }
        }

        return $instance;
    }

    /**
     * Resolves and calls a method on a given class, handling all parameter injections.
     *
     * @param object|string $classOrCallable
     * @param string|null   $method The method to be called.
     * @param array         $overrides
     *
     * @return mixed The result of the method invocation.
     * @throws ContainerException
     * @throws ReflectionException
     */
    public function call(object|string $classOrCallable, ?string $method = null, array $overrides = []): mixed
    {
        // Resolve callable and reflection
        [$objectOrClosure, $reflection] = $this->resolveCallable($classOrCallable, $method);

        // Resolve parameters
        $params = $reflection->getParameters();
        $resolvedParams = array_map(fn(ReflectionParameter $param) => $this->resolveParameterWithOverrides(
            $param,
            $overrides
        ), $params);

        // Invoke the callable
        return $reflection instanceof ReflectionMethod
            ? $reflection->invokeArgs($objectOrClosure, $resolvedParams)  // For class methods
            : $reflection->invokeArgs($resolvedParams);  // For closures
    }

    private function resolveCallable(object|string $classOrCallable, ?string $method): array
    {
        // If a method is provided, it's a class method
        if (!is_null($method)) {
            $object = is_string($classOrCallable) ? $this->get($classOrCallable) : $classOrCallable;
            $reflection = new ReflectionMethod($object, $method);

            return [$object, $reflection];
        }

        // If it's a closure, reflect on the closure
        if ($classOrCallable instanceof Closure) {
            return [null, new ReflectionFunction($classOrCallable)];
        }

        // It's neither a class method nor a closure...
        throw new ContainerException("Unsupported callable type: must be class method or closure.");
    }

    /**
     * Resolves a parameter by looking it up in the container.
     *
     * @param ReflectionParameter $param The reflection parameter to resolve.
     *
     * @return mixed The resolved parameter value.
     * @throws ContainerException If the parameter is a primitive type and cannot be resolved.
     */
    private function resolveParameter(ReflectionParameter $param): mixed
    {
        // Handle built-in types (e.g., int, string)
        if ($param->getType() !== null && $param->getType()->isBuiltin()) {
            if ($param->isOptional()) {
                return $param->getDefaultValue();
            }

            // Check if there's an override for this primitive in the registry
            $className = $param->getDeclaringClass()->getName();
            $paramName = $param->getName();
            $override = $this->overrideRegistry->getOverride($className, $paramName);

            if ($override !== null) {
                return $override;
            }

            throw ContainerException::forUnresolvedPrimitive($className, $paramName);
        }

        // Resolve by class type from the container
        return $this->get($param->getType()->getName());
    }
}
