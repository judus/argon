<?php

declare(strict_types=1);

namespace Maduser\Argon\Container;

use Closure;
use Maduser\Argon\Container\Contracts\ServiceProviderInterface;
use Maduser\Argon\Container\Contracts\TypeInterceptorInterface;
use Maduser\Argon\Container\Exceptions\ContainerException;
use Maduser\Argon\Container\Exceptions\NotFoundException;
use Maduser\Argon\Container\Support\NullServiceProxy;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use ReflectionException;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionNamedType;
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
    use ContextualBindingSupport;

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
     * @var ParameterRegistry Registry that manages global parameter overrides.
     */
    private ParameterRegistry $parameters;

    /**
     * @var array
     */
    protected array $tags = [];

    /**
     * Constructor for ServiceContainer.
     *
     * @param ParameterRegistry $parameterRegistry Registry for managing parameter overrides.
     */
    public function __construct(
        ParameterRegistry $parameterRegistry = new ParameterRegistry()
    ) {
        $this->contextualBindings = new ContextualBindingRegistry();
        $this->parameters = $parameterRegistry;
    }

    public function getServices(): array
    {
        return $this->services;
    }

    public function getTypeInterceptors(): array
    {
        return $this->typeInterceptors;
    }

    public function getTags(): array
    {
        return $this->tags;
    }

    public function getParameters(): ParameterRegistry
    {
        return $this->parameters;
    }

    /**
     * Registers a service provider and triggers the registration method.
     *
     * @param string $className
     * @return void
     * @throws ContainerException
     * @throws NotFoundException
     * @throws ReflectionException
     */
    public function registerServiceProvider(string $className): void
    {
        if (!class_exists($className)) {
            throw new ContainerException("Service provider class '$className' does not exist.");
        }

        if (!is_subclass_of($className, ServiceProviderInterface::class)) {
            throw new ContainerException("Service provider '$className' must implement ServiceProviderInterface.");
        }

        $this->singleton($className);
        $this->tag($className, ['service.provider']);

        /** @var ServiceProviderInterface $provider */
        $provider = $this->get($className);
        $provider->register($this);
    }

    /**
     * Boots all registered service providers by calling their boot methods.
     *
     * @return void
     * @throws ContainerException
     * @throws NotFoundException
     * @throws ReflectionException
     */
    public function bootServiceProviders(): void
    {
        $providers = $this->getTagged('service.provider');

        foreach ($providers as $provider) {
            $provider->boot($this);
        }
    }

    /**
     * Registers a type-based interceptor.
     *
     * @param string $interceptorClass
     * @return void
     * @throws ContainerException
     */
    public function registerTypeInterceptor(string $interceptorClass): void
    {
        if (!class_exists($interceptorClass)) {
            throw new ContainerException("Interceptor class '$interceptorClass' does not exist.");
        }

        if (!is_subclass_of($interceptorClass, TypeInterceptorInterface::class)) {
            throw new ContainerException("Interceptor '$interceptorClass' must implement TypeInterceptorInterface.");
        }

        $this->typeInterceptors[] = $interceptorClass;
    }

    /**
     * Registers a singleton service in the container.
     *
     * @param string $id The service identifier.
     * @param Closure|string|null $concrete The concrete class or closure to instantiate.
     * @return void
     * @throws ContainerException
     */
    public function singleton(string $id, Closure|string|null $concrete = null): void
    {
        $this->bind($id, $concrete ?? $id, true);
    }

    /**
     * Binds a service (transient or singleton) to the container.
     *
     * @param string $id The service identifier.
     * @param Closure|string|null $concrete The concrete class or closure to instantiate.
     * @param bool $isSingleton If true, the service will be treated as a singleton.
     * @return void
     * @throws ContainerException If the class does not exist.
     */
    public function bind(string $id, Closure|string|null $concrete = null, bool $isSingleton = false): void
    {
        $concrete ??= $id;

        if (!($concrete instanceof Closure) && !class_exists($concrete)) {
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
        // Convert callable to Closure to satisfy type requirements
        $factoryClosure = function () use ($factory): mixed {
            return call_user_func($factory);
        };

        $this->services[$id] = new ServiceDescriptor($factoryClosure, $isSingleton);
    }

    /**
     * Determines whether the container has a service registered by its identifier.
     *
     * @param string $id The service identifier.
     *
     * @return bool True if the service is registered in the container, false otherwise.
     */
    public function has(string $id): bool
    {
        return isset($this->services[$id]);
    }

    /**
     * Determines whether the container can resolve a service by its identifier, registered or not.
     *
     * @param string $id The service identifier.
     *
     * @return bool True if the service is registered in the container, false otherwise.
     */
    public function canResolve(string $id): bool
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
            if (!in_array($id, $this->tags[$tag] ?? [], true)) {
                $this->tags[$tag][] = $id;
            }
        }
    }

    /**
     * Retrieves all services associated with a tag.
     *
     * @param string $tag The tag to search for
     *
     * @return array The services associated with the given tag
     * @throws ContainerException
     * @throws NotFoundException
     * @throws ReflectionException
     */
    public function getTagged(string $tag): array
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
     * @template T of object
     * @param class-string<T>|string $id Service identifier or class name
     * @return object The resolved service instance
     * @psalm-return ($id is class-string<T> ? T : object)
     *
     * @throws ContainerException If the service cannot be instantiated
     * @throws NotFoundException If the service is not found
     * @throws ReflectionException If reflection fails
     */
    public function get(string $id): object
    {
        return $this->resolveService($id);
    }

    /**
     * Resolves a service by its identifier, optionally applying overrides.
     *
     * @param string $id The service identifier.
     * @param array $parameters Optional parameter overrides.
     *
     * @return object The resolved service instance.
     * @throws ContainerException If the service is not instantiable.
     * @throws NotFoundException If the service is not found.
     * @throws ReflectionException
     */
    private function resolveService(string $id, array $parameters = []): object
    {
        $this->checkCircularDependency($id);

        // Check if the service is registered or attempt to resolve it by class
        if (!isset($this->services[$id])) {
            if (class_exists($id)) {
                $reflection = $this->getReflection($id);

                if (!$reflection->isInstantiable()) {
                    throw ContainerException::forNonInstantiableClass($id, $reflection->getName());
                }

                $instance = $this->resolveClass($id, $parameters);
                return $this->applyTypeInterceptors($instance);
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
            $instance = $this->resolveClass($concrete, $parameters);
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
     * @throws ReflectionException
     */
    private function getReflection(string $className): ReflectionClass
    {
        if (!isset($this->reflectionCache[$className])) {
            if (!class_exists($className) && !interface_exists($className) && !trait_exists($className)) {
                throw new ReflectionException("Class, interface, or trait '$className' does not exist");
            }
            $this->reflectionCache[$className] = new ReflectionClass($className);
        }
        return $this->reflectionCache[$className];
    }

    /**
     * Resolves a class and its dependencies, using optional overrides.
     *
     * @param string $className The class name to resolve.
     * @param array $parameters Optional parameter overrides.
     * @return object The instantiated class.
     * @throws ContainerException If the class cannot be instantiated.
     * @throws ReflectionException
     * @throws NotFoundException
     */
    private function resolveClass(string $className, array $parameters = []): object
    {
        // Check if the className is bound to a different concrete class
        if (isset($this->services[$className])) {
            $descriptor = $this->services[$className];
            $concrete = $descriptor->getConcrete();

            if ($concrete instanceof Closure) {
                return $concrete();
            }

            // Only recurse if concrete is NOT the same as className
            if ($concrete !== $className) {
                return $this->resolveClass($concrete, $parameters);
            }

            // Else: fall through to regular instantiation logic below
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
            // Add class_exists check to help Psalm understand this is valid
            if (!class_exists($className)) {
                throw new ContainerException("Class '$className' does not exist");
            }
            /** @var class-string $className */
            return new $className();
        }

        // Resolve constructor parameters
        $dependencies = array_map(
            fn(ReflectionParameter $param) => $this->resolveParameters($param, $parameters),
            $constructor->getParameters()
        );

        return $reflection->newInstanceArgs($dependencies);
    }

    /**
     * Resolves a parameter using global and specific overrides.
     *
     * @param ReflectionParameter $param The reflection parameter to resolve.
     * @param array $parameters Optional parameter overrides.
     * @return mixed|\static The resolved parameter value.
     * @throws ReflectionException
     * @throws ContainerException
     * @throws NotFoundException
     */
    private function resolveParameters(ReflectionParameter $param, array $parameters = []): mixed
    {
        $className = $param->getDeclaringClass() ? $param->getDeclaringClass()->getName() : 'unknown class';
        $paramName = $param->getName();

        $mergedParameters = array_merge(
            $this->parameters->get($className),
            $parameters
        );

        if (isset($mergedParameters[$paramName])) {
            return $mergedParameters[$paramName];
        }

        /** @var ReflectionNamedType|null $type */
        $type = $param->getType();

        if ($type !== null && !$type->isBuiltin()) {
            $typeName = $type->getName();
            if ($this->contextualBindings->has($className, $typeName)) {
                return $this->resolveContextual($className, $typeName);
            }

            return $this->get($typeName);
        }

        if ($param->isOptional()) {
            return $param->getDefaultValue();
        }

        throw ContainerException::forUnresolvedPrimitive($className, $paramName);
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
        foreach ($this->typeInterceptors as $interceptorClass) {
            if ($interceptorClass::supports($instance)) {
                $interceptor = new $interceptorClass();
                $interceptor->intercept($instance);
            }
        }

        return $instance;
    }

    /**
     * Resolves and calls a method on a given class, handling all parameter injections.
     *
     * @param object|string $classOrCallable
     * @param string|null $method The method to be called.
     * @param array $parameters
     *
     * @return mixed The result of the method invocation.
     * @throws ContainerException
     * @throws ReflectionException
     * @throws NotFoundException
     */
    public function call(object|string $classOrCallable, ?string $method = null, array $parameters = []): mixed
    {
        // Resolve callable and reflection
        [$objectOrClosure, $reflection] = $this->resolveCallable($classOrCallable, $method);

        // Resolve parameters
        $params = $reflection->getParameters();
        $resolvedParams = array_map(fn(ReflectionParameter $param) => $this->resolveParameters(
            $param,
            $parameters
        ), $params);

        // Invoke the callable
        return $reflection instanceof ReflectionMethod
            ? $reflection->invokeArgs($objectOrClosure, $resolvedParams)  // For class methods
            : $reflection->invokeArgs($resolvedParams);  // For closures
    }

    /**
     * @throws ReflectionException
     * @throws ContainerException
     * @throws NotFoundException
     */
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
     * @throws ReflectionException
     * @throws NotFoundException
     */
    private function resolveParameter(ReflectionParameter $param): mixed
    {
        // Handle built-in types (e.g., int, string)
        /** @var ReflectionNamedType|null $type */
        $type = $param->getType();
        if ($type !== null && $type->isBuiltin()) {
            if ($param->isOptional()) {
                return $param->getDefaultValue();
            }

            // Check if there's an override for this primitive in the registry
            $className = $param->getDeclaringClass() ? $param->getDeclaringClass()->getName() : 'unknown';
            $paramName = $param->getName();
            $parameter = $this->parameters->getScoped($className, $paramName);

            if ($parameter !== null) {
                return $parameter;
            }

            throw ContainerException::forUnresolvedPrimitive($className, $paramName);
        }

        // Resolve by class type from the container
        if ($type === null) {
            throw new ContainerException("Parameter '{$param->getName()}' has no type hint and cannot be resolved");
        }

        return $this->get($type->getName());
    }

    /**
     * Conditionally access a service if it exists.
     * Returns a proxy that silently ignores calls if service is missing.
     *
     * @param string $id
     * @return object
     * @throws ContainerException
     * @throws NotFoundException
     * @throws ReflectionException
     */
    public function if(string $id): object
    {
        if (!$this->has($id)) {
            return new NullServiceProxy();
        }

        return $this->get($id);
    }
}
