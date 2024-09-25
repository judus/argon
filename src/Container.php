<?php

namespace Maduser\Argon;

use Closure;
use Exception;
use Maduser\Argon\Container\Provider;
use ReflectionException;

class Container
{
    /**
     * Gets the current provider instance, initializing the application if necessary.
     *
     * @return Provider The service provider instance
     * @throws Exception If initialization fails
     */
    protected static Provider $provider;

    /**
     * Gets the Provider, initializing it lazily if not already set.
     *
     * @return Provider The service provider instance
     * @throws Exception
     */
    public static function getProvider(): Provider
    {
        if (!isset(self::$provider)) {
            self::$provider = new Provider();
        }

        return self::$provider;
    }

    /**
     * Registers one or more services in the current provider context.
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
     * Binds an interface to a concrete class in the provider or accepts an array of bindings.
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
     * Registers a singleton service in the provider.
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
     * Resolves a service from the provider, injecting dependencies as needed.
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

    /**
     * Adds a type hook to the resolver, which triggers a specific handler for that type.
     *
     * @param string            $type    The type (class/interface) to watch for
     * @param Closure|callable $handler The handler (closure or callable class) to execute
     *
     * @throws Exception
     */
    public static function addTypeHook(string $type, Closure|callable $handler): void
    {
        self::getProvider()->getResolver()->addTypeHook($type, $handler);
    }
}