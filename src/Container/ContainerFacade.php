<?php

namespace Maduser\Argon\Container;

use Exception;
use ReflectionException;

class ContainerFacade
{
    /**
     * Gets the current container instance, initializing the application if necessary.
     *
     * @return ServiceContainer The service container instance
     * @throws Exception If initialization fails
     */
    protected static ?ServiceContainer $container = null;

    /**
     * Registers one or more services in the current container context.
     *
     * @param string|array $id    A service name and class, or an array of services (name => class)
     * @param string|null  $class The class to register (if $services is a string)
     *
     * @throws Exception If registration fails
     */
    public static function set(string|array $id, ?string $class = null): void
    {
        self::container()->set($id, $class);
    }

    /**
     * Gets the ServiceContainer, initializing it lazily if not already set.
     *
     * @return ServiceContainer The service container instance
     *
     * @throws Exception
     */
    public static function container(): ServiceContainer
    {
        if (is_null(self::$container)) {
            self::$container = new ServiceContainer();
        }

        return self::$container;
    }

    /**
     * Resolves a service from the container, injecting dependencies as needed.
     *
     * @param string     $id     The name of the service to resolve
     * @param array|null $params Optional parameters for instantiation
     *
     * @return mixed The resolved service instance
     * @throws Exception If the service cannot be resolved
     */
    public static function get(string $id, ?array $params = []): mixed
    {
        return self::container()->get($id, $params);
    }

    /**
     * Creates a new instance of a class, bypassing singleton registration.
     *
     * @param string $id   The class name to instantiate
     * @param array|null $params Optional parameters for instantiation
     *
     * @return object The instantiated class
     * @throws ReflectionException If instantiation fails
     * @throws Exception If the class cannot be resolved
     */
    public static function make(string $id, ?array $params = []): object
    {
        return self::container()->make($id, $params);
    }
}
