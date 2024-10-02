<?php

namespace Maduser\Argon\Container;

use Closure;
use Exception;
use Psr\Container\ContainerExceptionInterface;
use ReflectionException;

class ContainerFacade
{
    /**
     * @var ServiceContainer|null Holds the current container instance.
     */
    protected static ?ServiceContainer $container = null;

    /**
     * Registers one or more services in the current container context.
     *
     * @param string|array        $id    A service name and class, or an array of services (name => class)
     * @param string|Closure|null $class The class or closure to register (if $id is a string)
     *
     * @throws Exception|ContainerExceptionInterface If registration fails
     */
    public static function set(string|array $id, string|Closure|null $class = null): void
    {
        self::container()->set($id, $class);
    }

    /**
     * Gets the current container instance, initializing the application if necessary.
     *
     * @return ServiceContainer The service container instance
     * @throws Exception If initialization fails
     */
    public static function container(): ServiceContainer
    {
        if (is_null(self::$container)) {
            self::$container = new ServiceContainer();
        }

        return self::$container;
    }

    /**
     * Registers a singleton service in the current container context.
     *
     * @param string|array        $id    A service name and class, or an array of services (name => class)
     * @param string|Closure|null $class The class or closure to register (if $id is a string)
     *
     * @throws Exception|ContainerExceptionInterface If registration fails
     */
    public static function singleton(string|array $id, string|Closure|null $class = null): void
    {
        self::container()->singleton($id, $class);
    }

    /**
     * Binds an interface to a concrete class in the container.
     *
     * @param string|array   $interface The interface or array of bindings
     * @param string|Closure $concrete  The concrete class or closure
     *
     * @throws Exception If binding fails
     */
    public static function bind(string|array $interface, string|Closure $concrete): void
    {
        self::container()->bind($interface, $concrete);
    }

    /**
     * Resolves a service from the container, injecting dependencies as needed.
     *
     * @param string     $id     The name of the service to resolve
     * @param array|null $params Optional parameters for instantiation
     *
     * @return mixed The resolved service instance
     * @throws Exception|ContainerExceptionInterface If the service cannot be resolved
     */
    public static function get(string $id, ?array $params = []): mixed
    {
        return self::container()->get($id, $params);
    }

    /**
     * Resolves a service from the container conditionally, returns null or a NullServiceHandler if not found.
     *
     * @param string $id The name of the service to resolve conditionally
     *
     * @return mixed|null The resolved service instance or a null-safe handler
     * @throws Exception
     */
    public static function if(string $id): ?object
    {
        return self::container()->if($id);
    }

    /**
     * Creates a new instance of a class, bypassing singleton registration.
     *
     * @param string     $id     The class name to instantiate
     * @param array|null $params Optional parameters for instantiation
     *
     * @return object The instantiated class
     * @throws ReflectionException If instantiation fails
     * @throws Exception|ContainerExceptionInterface If the class cannot be resolved
     */
    public static function make(string $id, ?array $params = []): object
    {
        return self::container()->make($id, $params);
    }

    /**
     * Registers an "on register" hook for a specific type.
     *
     * @param string   $type    The class or interface to hook into
     * @param callable $handler The handler to invoke for this type
     *
     * @throws Exception
     */
    public static function onRegister(string $type, callable $handler): void
    {
        self::container()->onRegister($type, $handler);
    }

    /**
     * Registers an "on resolve" hook for a specific type.
     *
     * @param string   $type    The class or interface to hook into
     * @param callable $handler The handler to invoke for this type
     *
     * @throws Exception
     */
    public static function onResolve(string $type, callable $handler): void
    {
        self::container()->onResolve($type, $handler);
    }
}
