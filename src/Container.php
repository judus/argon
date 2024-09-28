<?php

namespace Maduser\Argon;

use Closure;
use Exception;
use Maduser\Argon\Container\ServiceContainer;
use ReflectionException;

class Container
{
    /**
     * Gets the current container instance, initializing the application if necessary.
     *
     * @return Container The service container instance
     * @throws Exception If initialization fails
     */
    protected static ServiceContainer $provider;

    /**
     * Gets the ServiceContainer, initializing it lazily if not already set.
     *
     * @return ServiceContainer The service container instance
     */
    public static function getProvider(): ServiceContainer
    {
        if (!isset(self::$provider)) {
            self::$provider = new ServiceContainer();
        }

        return self::$provider;
    }

    /**
     * Registers one or more services in the current container context.
     *
     * @param string|array $services A service name and class, or an array of services (name => class)
     * @param string|null  $class    The class to register (if $services is a string)
     *
     * @throws Exception If registration fails
     */
    public static function register(string|array $services, ?string $class = null): void
    {
        self::getProvider()->register($services, $class);
    }

    /**
     * Binds an interface to a concrete class in the container or accepts an array of bindings.
     *
     * @param string|array $interface The interface name or an array of interface => class bindings
     * @param string|null  $class     The class to bind to the interface (if $interface is a string)
     *
     * @throws Exception If binding fails
     */
    public static function bind(string|array $interface, ?string $class = null): void
    {
        self::getProvider()->bind($interface, $class);
    }

    /**
     * Registers a singleton service in the container.
     *
     * @param string     $name   The name of the singleton
     * @param mixed|null $object The singleton instance or closure
     *
     * @throws Exception If registration fails
     */
    public static function singleton(string $name, mixed $object = null): void
    {
        self::getProvider()->singleton($name, $object);
    }

    /**
     * Resolves a service from the container, injecting dependencies as needed.
     *
     * @param string     $name   The name of the service to resolve
     * @param array|null $params Optional parameters for instantiation
     *
     * @return mixed The resolved service instance
     * @throws Exception If the service cannot be resolved
     */
    public static function resolve(string $name, ?array $params = []): mixed
    {
        return self::getProvider()->resolve($name, $params);
    }

    /**
     * Creates a new instance of a class, bypassing singleton registration.
     *
     * @param string     $name   The class name to instantiate
     * @param array|null $params Optional parameters for instantiation
     *
     * @return object The instantiated class
     * @throws ReflectionException If instantiation fails
     * @throws Exception If the class cannot be resolved
     */
    public static function make(string $name, ?array $params = []): object
    {
        return self::getProvider()->make($name, $params);
    }
}
