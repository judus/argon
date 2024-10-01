<?php

namespace Maduser\Argon\Container;

use Closure;
use Exception;
use Maduser\Argon\Container\Contracts\Authorizable;
use Maduser\Argon\Container\Contracts\Validatable;
use Maduser\Argon\Container\Exceptions\ContainerException;
use Maduser\Argon\Container\Exceptions\ServiceNotFoundException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
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
    use Taggable;

    public bool $enableAutoResolve = true;
    private Factory $factory;
    private Resolver $resolver;
    private Registry $services;
    private Registry $bindings;
    private array $conditionalBindings = [];

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

        // Register default hooks for service providers
        $this->registerDefaultHooks();
    }

    /**
     * Registers default hooks for service providers.
     */
    private function registerDefaultHooks(): void
    {
        // Set up the onRegister hook for ServiceProvider
        $this->onRegister(ServiceProvider::class, function (ServiceDescriptor $descriptor) {
            $provider = $this->make($descriptor->getDefinition());
            $provider->register();
            // TODO: Implement setProvider method
            // Store and reuse the provider instance
            // $descriptor->setProvider($provider);

            return $provider;
        });

        // Set up the onResolve hook for ServiceProvider
        $this->onResolve(ServiceProvider::class, function (ServiceProvider $provider) {
            return $provider->resolve();
        });

        // Register the default onResolve hook for Authorizable instances
        $this->onResolve(Authorizable::class, function (Authorizable $authorizable) {
            $authorizable->authorize();
            return $authorizable;
        });

        // Register the default onResolve hook for Validatable instances
        $this->onResolve(Validatable::class, function (Validatable $validatable) {
            $validatable->validate();
            return $validatable;
        });
    }

    /**
     * Registers an "on register" hook for a specific type.
     *
     * @param string   $type    The class or interface to hook into
     * @param callable $handler The handler to invoke for this type
     */
    public function onRegister(string $type, callable $handler): void
    {
        $this->postRegister($type, $handler);
    }

    /**
     * Creates a new instance of a class using the factory.
     *
     * @param string     $class  The class name to instantiate
     * @param array|null $params Optional parameters for instantiation
     *
     * @return object The instantiated class
     *
     * @throws ContainerExceptionInterface
     */
    public function make(string $class, ?array $params = []): object
    {
        try {
            return $this->factory->make($class, $params);
        } catch (ReflectionException $e) {
            throw new ContainerException("Error instantiating class '$class': " . $e->getMessage(), $e);
        }
    }

    /**
     * Registers an "on resolve" hook for a specific type.
     *
     * @param string   $type    The class or interface to hook into
     * @param callable $handler The handler to invoke for this type
     */
    public function onResolve(string $type, callable $handler): void
    {
        $this->postResolve($type, $handler);
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
        if ($this->services->has($id)) {
            return true;
        }

        $alias = basename(str_replace('\\', '/', $id));

        return $this->services->has($alias);
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
     *
     * @throws ContainerExceptionInterface
     */
    public function get(string $id, ?array $params = []): mixed
    {
        try {
            if ($this->services->has($id)) {
                return $this->resolver->resolve($id, $params);
            }

            if ($this->bindings->has($id)) {
                $concreteClass = $this->bindings->get($id);
                return $this->resolver->resolveOrMake($concreteClass, $params);
            }

            throw new ServiceNotFoundException($id);

        } catch (ReflectionException $e) {
            throw new ContainerException("Error resolving service '$id': " . $e->getMessage(), $e);
        } catch (Exception $e) {
            throw new ContainerException("General error resolving service '$id': " . $e->getMessage(), $e);
        }
    }

    /**
     * Resolves a service or attempts to create a new instance
     *
     * @param string     $aliasOrClass
     * @param array|null $params
     *
     * @return mixed
     *
     * @throws ContainerExceptionInterface
     */
    public function resolveOrMake(string|Closure $aliasOrClass, ?array $params = []): mixed
    {
        return $this->resolver->resolveOrMake($aliasOrClass, $params);
    }

    public function when(string $requesterClass): ConditionalBinding
    {
        $conditionalBinding = new ConditionalBinding($requesterClass, $this);
        $this->conditionalBindings[] = $conditionalBinding;

        return $conditionalBinding;
    }

    /**
     * Binds an interface to a class or closure.
     *
     * @param string|array   $interface The interface name or an array of mappings
     * @param string|Closure $concrete  The concrete class or closure (if $interface is a string)
     *
     * @throws ContainerException
     */
    public function bind(string|array $interface, string|Closure $concrete): void
    {
        if (is_array($interface)) {
            $this->registerMultipleBindings($interface);
        } else {
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
     * @param string|array        $alias      The alias or an array of services
     * @param string|Closure|null $definition The class or closure definition (if $alias is a string)
     * @param array|null          $params     Parameters for the service (optional)
     *
     * @return ServiceDescriptor|null
     *
     * @throws ContainerExceptionInterface
     */
    public function set(
        string|array $alias,
        string|Closure|null $definition = null,
        ?array $params = []
    ): ?ServiceDescriptor {
        try {
            if (is_array($alias)) {
                return $this->registerMultipleServices($alias, $params);
            } else {
                if (is_null($definition)) {
                    throw new ContainerException('Class or closure must be provided for service registration.');
                }

                return $this->registerService($alias, $definition, $params, false);
            }
        } catch (Exception $e) {
            throw new ContainerException("Error setting service '$alias': " . $e->getMessage(), $e);
        }
    }

    /**
     * Registers multiple services.
     *
     * @param array      $services Array of services to register
     * @param array|null $params   Optional parameters for the services
     *
     * @return ServiceDescriptor|null
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
     * @param string         $id          The alias for the service
     * @param string|Closure $definition  The class or closure for the service
     * @param array|null     $params      Optional parameters for the service
     * @param bool           $isSingleton Whether the service is a singleton
     *
     * @return ServiceDescriptor
     *
     * @throws ContainerException
     */
    private function registerService(
        string $id,
        string|Closure $definition,
        ?array $params,
        bool $isSingleton
    ): ServiceDescriptor {

        if (is_string($definition) && !class_exists($definition)) {
            throw new ContainerException("Class '$definition' does not exist.");
        }

        $descriptor = new ServiceDescriptor($id, $definition, $isSingleton, $params);
        $this->services->add($id, $descriptor);
        $this->handleSetterHooks($descriptor, $id);

        return $descriptor;
    }

    /**
     * Registers a singleton service.
     *
     * @param string              $id         The name of the singleton
     * @param string|Closure|null $definition The class or closure definition (optional)
     * @param array|null          $params     Parameters for the service (optional)
     *
     * @return ServiceDescriptor
     *
     * @throws ContainerExceptionInterface
     */
    public function singleton(
        string $id,
        string|Closure|null $definition = null,
        ?array $params = []
    ): ServiceDescriptor {
        try {
            $definition = $definition ?? $id;

            return $this->registerService($id, $definition, $params, true);
        } catch (Exception $e) {
            throw new ContainerException("Error registering singleton '$id': " . $e->getMessage(), $e);
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
     *
     * @throws ContainerExceptionInterface|NotFoundExceptionInterface
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
                    throw new ContainerException("Class '$id' is not instantiable.");
                }
            }

            throw new ServiceNotFoundException($id);
        } catch (ReflectionException $e) {
            throw new ContainerException("Reflection error for service '$id': " . $e->getMessage(), $e);
        }
    }

    /**
     * @throws ContainerExceptionInterface
     */
    public function execute(Closure $closure, array $optionalParams = [], ?string $requester = null): mixed
    {
        try {
            $reflection = new ReflectionFunction($closure);
            $dependencies = [];

            foreach ($reflection->getParameters() as $parameter) {
                $paramType = $parameter->getType();

                if ($paramType instanceof ReflectionNamedType && !$paramType->isBuiltin()) {
                    // Inject user-defined dependencies (e.g., AService $aService)
                    $dependencies[] = $this->resolveOrMake($paramType->getName());
                } else {
                    // Handle optional or positional parameters
                    $dependencies[] = $optionalParams[$parameter->getName()] ?? array_shift($optionalParams);
                }
            }

            // Append the `$requester` at the end of the parameter list
            $dependencies[] = $requester;

            return $reflection->invokeArgs($dependencies);
        } catch (ReflectionException $e) {
            throw new Exception("Error executing closure: " . $e->getMessage());
        }
    }

    public function if(string $service): ?object
    {
        // Check if the service exists in the container
        if ($this->has($service)) {
            // Return the resolved service
            return $this->get($service);
        }

        // Return a null-safe handler that does nothing if the service doesn't exist
        return new NullServiceHandler();
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
}
