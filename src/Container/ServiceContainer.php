<?php

namespace Maduser\Argon\Container;

use Exception;
use ReflectionClass;
use ReflectionException;

/**
 * Class ServiceContainer
 *
 * Manages services, singletons, bindings, and providers, with dependency injection and resolution.
 *
 * @package Maduser\Argon\Container
 */
class ServiceContainer
{
    private Registry $providers;
    private Registry $bindings;
    private Registry $singletons;
    private Resolver $resolver;
    private Factory $injector;

    private array $setterHooks = [];

    protected bool $autoResolveUnregistered = true;

    public function setAutoResolveUnregistered(bool $value): void
    {
        $this->autoResolveUnregistered = $value;
    }

    /**
     * ServiceContainer constructor.
     *
     * @param array|null $providers An array of service providers
     * @param array|null $bindings  An array of interface to class bindings
     */
    public function __construct(?array $providers = [], ?array $bindings = [])
    {
        $this->injector = new Factory($this);
        $this->resolver = new Resolver($this);  // Initializes the resolver with the current container
        $this->setBindings($bindings);
        $this->setProviders($providers);
        $this->singletons = new Registry();
        $this->singleton(self::class, $this);
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
     * Registers a service or container in the container.
     *
     * @param string          $name   The name of the service or container
     * @param string|callable $class  The class name or a callable to resolve the service
     * @param array|null      $params Optional parameters to be passed (for providers)
     *
     * @throws Exception
     */
    public function register(string $name, string|callable $class, array $params = null): void
    {
        $instance = $this->handleSetterHooks($name, $class);
        $this->providers->add($name, $instance ?: $class);
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
     * Handles setter hooks when a service is registered.
     *
     * @param string $serviceName The name of the service being registered.
     * @param string $className   The class name of the service.
     */
    private function handleSetterHooks(string $serviceName, string $className): mixed
    {
        foreach ($this->setterHooks as $type => $handler) {
            if (is_subclass_of($className, $type) || $className === $type) {
                return $handler($className);
            }
        }

        return null;
    }

    /**
     * Registers a singleton. If already registered, it returns the instance.
     *
     * @param string     $name   The name of the singleton
     * @param mixed|null $object The singleton instance or closure to resolve it
     *
     * @return mixed The registered singleton instance
     */
    public function singleton(string $name, mixed $object = null): mixed
    {
        if ($object) {
            $object = is_callable($object) ? $object() : $object;
            $this->singletons->add($name, $object);
        }

        return $this->singletons->get($name);
    }

    /**
     * Resolves a service from the container using the resolver (handles hooks).
     *
     * @param string     $name   The name of the service to resolve
     * @param array|null $params Optional parameters for instantiation
     *
     * @return mixed The resolved service instance
     * @throws Exception
     */
    public function resolve(string $name, ?array $params = []): mixed
    {
        return $this->resolver->resolve($name, $params);
    }

    /**
     * Creates a new instance of a class using the injector.
     *
     * @param string     $name   The class name to instantiate
     * @param array|null $params Optional parameters for instantiation
     *
     * @return object The instantiated class
     * @throws ReflectionException
     * @throws Exception
     */
    public function make(string $name, ?array $params = []): object
    {
        return $this->injector->make($name, $params);
    }

    /**
     * Binds an interface to a class in the container.
     *
     * @param string $interface The interface name
     * @param string $class     The class to bind to the interface
     */
    public function bind(string $interface, string $class): void
    {
        $this->bindings->add($interface, $class);
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
     * Gets a container or singleton, or throws an exception if not found.
     *
     * @param string $name The name of the container or singleton
     *
     * @return mixed The container instance
     * @throws Exception If the container or singleton cannot be found
     */
    public function getProvider(string $name): mixed
    {
        if ($this->singletons->has($name)) {
            return $this->singletons->get($name);
        }

        if ($this->providers->has($name)) {
            return $this->providers->get($name);
        }

        $alias = basename(str_replace('\\', '/', $name));
        if ($this->singletons->has($alias)) {
            return $this->singletons->get($alias);
        }

        if ($this->providers->has($alias)) {
            return $this->providers->get($alias);
        }

        // Check if the class exists and is instantiable before throwing an exception
        if ($this->autoResolveUnregistered && class_exists($name)) {
            $reflectionClass = new ReflectionClass($name);
            if ($reflectionClass->isInstantiable()) {
                return $name;
            } else {
                throw new Exception("Class '{$name}' is not instantiable.");
            }
        }

        throw new Exception("ServiceContainer or singleton '{$name}' not found.");
    }

    /**
     * Attempts to get a container or singleton, or returns null if not found.
     *
     * @param string $name The name of the container or singleton
     *
     * @return mixed|null The container instance or null if not found
     */
    public function findProvider(string $name): mixed
    {
        try {
            return $this->getProvider($name);
        } catch (Exception $e) {
            return null;
        }
    }
}
