<?php

namespace Maduser\Argon\Container;

use Closure;
use Exception;
use ReflectionClass;
use ReflectionException;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use ReflectionNamedType;

/**
 * Class ServiceContainer
 *
 * Manages services, singletons, bindings, and providers, with dependency injection and resolution.
 *
 * @package Maduser\Argon\Container
 */
class ServiceContainer
{
    public bool $autoResolveUnregistered = true;
    private Registry $providers;
    private Registry $bindings;
    private Registry $singletons;
    private Resolver $resolver;
    private Factory $factory;
    private array $setterHooks = [];

    /**
     * ServiceContainer constructor.
     *
     * @param array $providers An array of service providers
     * @param array $bindings  An array of interface to class bindings
     */
    public function __construct(array $providers = [], array $bindings = [])
    {
        $this->factory = new Factory($this);
        $this->resolver = new Resolver($this);  // Initializes the resolver with the current container
        $this->providers = new Registry($providers);
        $this->bindings = new Registry($bindings);
        $this->singletons = new Registry();
    }

    public function setAutoResolveUnregistered(bool $value): void
    {
        $this->autoResolveUnregistered = $value;
    }

    /**
     * Sets the service providers.
     *
     * @param array $providers An array of service providers
     */
    public function setProviders(array $providers): void
    {
        $this->providers = new Registry($providers);
    }

    /**
     * Sets the interface to class bindings.
     *
     * @param array $bindings An array of bindings (interface to class mappings)
     */
    public function setBindings(array $bindings): void
    {
        $this->bindings = new Registry($bindings);
    }

    /**
     * Register multiple providers or a single provider in the container.
     *
     * @param string|array<string, string> $alias  The alias or an array of providers
     * @param string|null                  $class  The class name (if $alias is a string)
     * @param array|null                   $params Parameters for the class (optional)
     *
     * @return ServiceDescriptor|null Returns a ServiceDescriptor for single registration, null for multiple
     * @throws Exception
     */
    public function register(string|array $alias, ?string $class = null, ?array $params = []): ?ServiceDescriptor
    {
        if (is_array($alias)) {
            // Handle array of providers
            foreach ($alias as $providerAlias => $providerClass) {
                $descriptor = new ServiceDescriptor($providerAlias, $providerClass, false, $params, $this);
                $this->providers->add($providerAlias, $descriptor);
                $this->handleSetterHooks($descriptor, $providerAlias);
            }

            return null;
        } else {
            if (is_null($class)) {
                throw new Exception('Class name must be provided for single provider registration.');
            }
            // Handle single provider registration
            $descriptor = new ServiceDescriptor($alias, $class, false, $params, $this);
            $this->providers->add($alias, $descriptor);
            $this->handleSetterHooks($descriptor, $alias);

            return $descriptor;
        }
    }

    /**
     * Handles setter hooks when a service is registered.
     *
     * @param string $class
     */
    private function handleSetterHooks(ServiceDescriptor $descriptor, string $alias): void
    {
        $class = $descriptor->getClassName();

        foreach ($this->setterHooks as $type => $handler) {
            if (is_subclass_of($class, $type) || $class === $type) {
                $handler($descriptor, $alias);

                return;
            }
        }
    }

    /**
     * Adds a setter hook for a specific type.
     *
     * @param string   $type    The type or interface to hook into.
     * @param callable $handler The handler to invoke.
     */
    public function addSetterHook(string $type, callable $handler): void
    {
        $this->setterHooks[$type] = $handler;
    }

    /**
     * Registers a singleton. If already registered, it returns the instance.
     *
     * @param string      $alias  The name of the singleton
     * @param string|null $class
     * @param array|null  $params
     *
     * @return mixed The registered singleton instance
     */
    public function singleton(string $alias, ?string $class = null, ?array $params = []): ServiceDescriptor
    {
        // If class is not provided, assume it's the same as alias
        $class = $class ?? $alias;

        // Create the ServiceDescriptor with the singleton flag set to true
        $descriptor = new ServiceDescriptor($alias, $class, true, $params, $this);

        // Register the descriptor in the providers list
        $this->providers->add($alias, $descriptor);

        // Handle setter hooks
        $this->handleSetterHooks($descriptor, $alias);

        // Return the descriptor
        return $descriptor;
    }

    /**
     * Resolves a service from the container using the resolver (handles hooks).
     *
     * @param string     $alias
     * @param array|null $params Optional parameters for instantiation
     *
     * @return mixed The resolved service instance
     * @throws Exception
     */
    public function resolve(string $alias, ?array $params = []): mixed
    {
        return $this->resolver->resolve($alias, $params);
    }

    /**
     * Creates a new instance of a class using the injector.
     *
     * @param string     $class  The class name to instantiate
     * @param array|null $params Optional parameters for instantiation
     *
     * @return object The instantiated class
     * @throws ReflectionException
     * @throws Exception
     */
    public function make(string $class, ?array $params = []): object
    {
        return $this->factory->make($class, $params);
    }

    /**
     * Adds a pre-resolution hook.
     *
     * @param string   $type    The class or interface to hook into
     * @param callable $handler The handler to invoke for this type
     */
    public function addPreResolutionHook(string $type, callable $handler): void
    {
        $this->resolver->addPreResolutionHook($type, $handler);
    }

    /**
     * Adds a post-resolution hook.
     *
     * @param string   $type    The class or interface to hook into
     * @param callable $handler The handler to invoke for this type
     */
    public function addPostResolutionHook(string $type, callable $handler): void
    {
        $this->resolver->addPostResolutionHook($type, $handler);
    }

    /**
     * Checks if a container or singleton is registered.
     *
     * @param string $name The name of the container or singleton
     *
     * @return bool True if the container or singleton is registered, false otherwise
     */
    public function hasProvider(string $name): bool
    {
        if ($this->singletons->has($name) || $this->providers->has($name)) {
            return true;
        }

        $alias = basename(str_replace('\\', '/', $name));

        return $this->singletons->has($alias) || $this->providers->has($alias);
    }

    /**
     * Attempts to get a container or singleton, or returns null if not found.
     *
     * @param string $alias
     *
     * @return mixed|null The container instance or null if not found
     */
    public function findProvider(string $alias): mixed
    {
        try {
            return $this->geProvider($alias);
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Gets a container or singleton, or throws an exception if not found.
     *
     * @param string $alias The name of the container or singleton
     *
     * @return mixed The container instance
     * @throws Exception If the container or singleton cannot be found
     */
    public function geProvider(string $alias): mixed
    {
        if ($this->singletons->has($alias)) {
            return $this->singletons->get($alias);
        }

        if ($this->providers->has($alias)) {
            return $this->providers->get($alias);
        }

        $alias = basename(str_replace('\\', '/', $alias));
        if ($this->singletons->has($alias)) {
            return $this->singletons->get($alias);
        }

        if ($this->providers->has($alias)) {
            return $this->providers->get($alias);
        }

        // Check if the class exists and is instantiable before throwing an exception
        if ($this->autoResolveUnregistered && class_exists($alias)) {
            $reflectionClass = new ReflectionClass($alias);
            if ($reflectionClass->isInstantiable()) {
                return $alias;
            } else {
                throw new Exception("Class '{$alias}' is not instantiable.");
            }
        }

        throw new Exception("ServiceContainer or singleton '{$alias}' not found.");
    }

    public function providers(): Registry
    {
        return $this->providers;
    }

    /**
     * Binds an interface to a class in the container.
     *
     * @param string|array<string, string> $interface The interface name or an array of mappings
     * @param string|null  $concrete  The concrete class (if $interface is a string)
     */
    public function bind(string|array $interface, ?string $concrete = null): void
    {
        if (is_array($interface)) {
            // Handle array of bindings
            foreach ($interface as $int => $class) {
                $this->bindings->add($int, $class);
            }
        } else {

            // Handle single binding
            $this->bindings->add($interface, $concrete);
        }
    }

    public function bindings(): Registry
    {
        return $this->bindings;
    }

    public function hasBinding(string $interface): bool
    {
        return $this->bindings->has($interface);
    }

    public function getBinding(string $interface): ?string
    {
        return $this->bindings->get($interface);
    }

    /**
     * Create an alias for an existing service.
     *
     * @param string $alias  The alias name
     * @param string $target The original service name
     */
    public function alias(string $alias, string $target): void
    {
        // Register an alias in the container
        $this->providers->add($alias, $this->getServiceDescriptor($target));
    }

    public function getServiceDescriptor(string $alias): ?ServiceDescriptor
    {
        return $this->providers->get($alias);
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     */
    public function execute(callable $callable, array $optionalParams = []): mixed
    {
        $reflection = $this->getCallableReflection($callable);

        $dependencies = [];

        foreach ($reflection->getParameters() as $param) {
            $paramType = $param->getType();

            if ($paramType instanceof ReflectionNamedType && !$paramType->isBuiltin()) {
                // Resolve dependencies from the container instead of instantiating new ones
                $dependencies[] = $this->resolveOrMake($paramType->getName());
            } else {
                // Optional or non-class parameters
                $dependencies[] = $optionalParams[$param->getName()] ?? array_shift($optionalParams);
            }
        }

        return call_user_func_array($callable, $dependencies);
    }

    /**
     * @throws ReflectionException
     */
    private function getCallableReflection(callable $callable): ReflectionFunctionAbstract
    {
        if (is_array($callable)) {
            return new ReflectionMethod($callable[0], $callable[1]);
        }

        if (is_object($callable) && !$callable instanceof Closure) {
            return new ReflectionMethod($callable, '__invoke');
        }

        return new ReflectionFunction($callable);
    }

    public function resolveOrMake(string $aliasOrClass, ?array $params = []): mixed
    {
        return $this->resolver->resolveOrMake($aliasOrClass, $params);
    }
}
