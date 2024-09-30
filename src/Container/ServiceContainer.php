<?php

namespace Maduser\Argon\Container;

use Closure;
use Exception;
use Maduser\Argon\Container\Exceptions\ContainerErrorException;
use Maduser\Argon\Container\Exceptions\ServiceNotFoundException;
use Psr\Container\ContainerInterface;
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
class ServiceContainer implements ContainerInterface
{
    use Hookable;

    public bool $enableAutoResolve = true;
    private Registry $services;
    private Registry $bindings;
    private Registry $singletons;
    private Resolver $resolver;
    private Factory $factory;

    /**
     * ServiceContainer constructor.
     *
     * @param array $services
     * @param array $bindings An array of interface to class bindings
     */
    public function __construct(array $services = [], array $bindings = [])
    {
        $this->factory = new Factory($this);
        $this->resolver = new Resolver($this);
        $this->services = new Registry($services);
        $this->bindings = new Registry($bindings);
    }

    public function enableAutoResolve(bool $value): void
    {
        $this->enableAutoResolve = $value;
    }

    public function services(): Registry
    {
        return $this->services;
    }

    public function bindings(): Registry
    {
        return $this->bindings;
    }

    /**
     * Create an alias for an existing service.
     *
     * @param string $alias  The alias name
     * @param string $target The original service name
     */
    public function alias(string $alias, string $target): void
    {
        if (!$this->services->has($target)) {
            throw new ServiceNotFoundException($target);
        }
        $this->services->add($alias, $this->getServiceDescriptor($target));
    }

    /**
     * Checks if a container or singleton is registered.
     *
     * @param string $id The name of the container or singleton
     *
     * @return bool True if the container or singleton is registered, false otherwise
     */
    public function has(string $id): bool
    {
        if ($this->singletons->has($id) || $this->services->has($id)) {
            return true;
        }

        $alias = basename(str_replace('\\', '/', $id));

        return $this->singletons->has($alias) || $this->services->has($alias);
    }

    /**
     * @param string $alias
     *
     * @return ServiceDescriptor|null
     */
    public function getServiceDescriptor(string $alias): ?ServiceDescriptor
    {
        return $this->services->get($alias);
    }

    /**
     * Resolves a service from the container using the resolver (handles hooks).
     *
     * @param string     $id
     * @param array|null $params Optional parameters for instantiation
     *
     * @return mixed The resolved service instance
     * @throws Exception
     */
    public function get(string $id, ?array $params = []): mixed
    {
        try {
            return $this->resolver->resolve($id, $params);
        } catch (ReflectionException $e) {
            throw new ContainerErrorException("Error resolving service '$id': " . $e->getMessage(), $e);
        } catch (Exception $e) {
            throw new ContainerErrorException("General error resolving service '$id': " . $e->getMessage(), $e);
        }
    }

    /**
     * Creates a new instance of a class using the factory.
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
        try {
            return $this->factory->make($class, $params);
        } catch (ReflectionException $e) {
            throw new ContainerErrorException("Error instantiating class '$class': " . $e->getMessage(), $e);
        }
    }

    /**
     * Binds an interface to a class.
     *
     * @param string|array $interface The interface name or an array of mappings
     * @param string|null  $concrete  The concrete class (if $interface is a string)
     */
    public function bind(string|array $interface, ?string $concrete = null): void
    {
        if (is_array($interface)) {
            $this->registerMultipleBindings($interface);
        } else {
            if (is_null($concrete)) {
                throw new ContainerErrorException("Concrete class must be provided for binding '$interface'.");
            }
            $this->bindings->add($interface, $concrete);
        }
    }

    /**
     * Registers multiple interface bindings.
     *
     * @param array $bindings Array of interface to class bindings
     */
    private function registerMultipleBindings(array $bindings): void
    {
        foreach ($bindings as $interface => $concrete) {
            $this->bindings->add($interface, $concrete);
        }
    }

    /**
     * Registers multiple or single services in the container.
     *
     * @param string|array $alias  The alias or an array of services
     * @param string|null  $class  The class name (if $alias is a string)
     * @param array|null   $params Parameters for the service (optional)
     *
     * @return ServiceDescriptor|null
     * @throws ContainerErrorException
     */
    public function set(string|array $alias, ?string $class = null, ?array $params = []): ?ServiceDescriptor
    {
        try {
            if (is_array($alias)) {
                return $this->registerMultipleServices($alias, $params);
            } else {
                if (is_null($class)) {
                    throw new ContainerErrorException('Class name must be provided for single service registration.');
                }

                return $this->registerService($alias, $class, $params, false);
            }
        } catch (Exception $e) {
            throw new ContainerErrorException("Error setting service '$alias': " . $e->getMessage(), $e);
        }
    }

    /**
     * Registers multiple services.
     *
     * @param array      $services Array of services to register
     * @param array|null $params   Optional parameters for the services
     *
     * @return null
     */
    private function registerMultipleServices(array $services, ?array $params = []): ?ServiceDescriptor
    {
        foreach ($services as $providerAlias => $providerClass) {
            $this->registerService($providerAlias, $providerClass, $params, false);
        }

        return null;
    }

    /**
     * Registers a service descriptor.
     *
     * @param string     $alias       The alias for the service
     * @param string     $class       The class name for the service
     * @param array|null $params      Optional parameters for the service
     * @param bool       $isSingleton Whether the service is a singleton
     *
     * @return ServiceDescriptor
     */
    private function registerService(string $alias, string $class, ?array $params, bool $isSingleton): ServiceDescriptor
    {
        $descriptor = new ServiceDescriptor($alias, $class, $isSingleton, $params);
        $this->services->add($alias, $descriptor);
        $this->handleSetterHooks($descriptor, $alias);

        return $descriptor;
    }

    /**
     * Registers a singleton service.
     *
     * @param string      $id     The name of the singleton
     * @param string|null $class  The class name (optional)
     * @param array|null  $params Parameters for the service (optional)
     *
     * @return ServiceDescriptor
     * @throws ContainerErrorException
     */
    public function singleton(string $id, ?string $class = null, ?array $params = []): ServiceDescriptor
    {
        try {
            $class = $class ?? $id;

            return $this->registerService($id, $class, $params, true);
        } catch (Exception $e) {
            throw new ContainerErrorException("Error registering singleton '$id': " . $e->getMessage(), $e);
        }
    }

    /**
     * Attempts to get a container or singleton, or returns null if not found.
     *
     * @param string $id
     *
     * @return mixed|null The container instance or null if not found
     */
    public function find(string $id): mixed
    {
        try {
            return $this->findAny($id);
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Gets a service or singleton, or throws an exception if not found.
     *
     * @param string $id The name of the container or singleton
     *
     * @return mixed The container instance
     * @throws Exception If the container or singleton cannot be found
     */
    public function findAny(string $id): mixed
    {
        try {
            if ($this->services->has($id)) {
                return $this->services->get($id);
            }

            if ($this->enableAutoResolve && class_exists($id)) {
                $reflectionClass = new ReflectionClass($id);
                if ($reflectionClass->isInstantiable()) {
                    return $id;
                } else {
                    throw new ContainerErrorException("Class '$id' is not instantiable.");
                }
            }

            throw new ServiceNotFoundException($id);
        } catch (ReflectionException $e) {
            throw new ContainerErrorException("Reflection error for service '$id': " . $e->getMessage(), $e);
        }
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     */
    public function execute(callable $callable, array $optionalParams = []): mixed
    {
        try {
            $reflection = $this->getCallableReflection($callable);
            $dependencies = [];

            foreach ($reflection->getParameters() as $param) {
                $paramType = $param->getType();
                if ($paramType instanceof ReflectionNamedType && !$paramType->isBuiltin()) {
                    $dependencies[] = $this->resolveOrMake($paramType->getName());
                } else {
                    $dependencies[] = $optionalParams[$param->getName()] ?? array_shift($optionalParams);
                }
            }

            return call_user_func_array($callable, $dependencies);
        } catch (ReflectionException $e) {
            throw new ContainerErrorException("Error executing callable: " . $e->getMessage(), $e);
        }
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

    /**
     * Resolves a service or attempts to create a new instance
     *
     * @param string     $aliasOrClass
     * @param array|null $params
     *
     * @return mixed
     */
    public function resolveOrMake(string $aliasOrClass, ?array $params = []): mixed
    {
        return $this->resolver->resolveOrMake($aliasOrClass, $params);
    }

}
