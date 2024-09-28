<?php

declare(strict_types=1);

namespace Maduser\Argon\Container;

use Exception;

/**
 * Class Resolver
 *
 * Resolves services, handling providers, singletons, bindings, and hooks.
 *
 * @package Maduser\Argon\Container
 */
class Resolver
{
    /**
     * @var ServiceContainer The service container managing bindings, singletons, and providers
     */
    private ServiceContainer $container;

    /**
     * @var array Pre-resolution hooks
     */
    private array $preResolutionHooks = [];

    /**
     * @var array Post-resolution hooks
     * @psalm-var array<string, callable|string>
     */
    private array $postResolutionHooks = [];

    /**
     * Resolver constructor.
     *
     * @param ServiceContainer $provider The service container instance
     */
    public function __construct(ServiceContainer $provider)
    {
        $this->container = $provider;
    }

    /**
     * Adds a pre-resolution hook for a specific type.
     *
     * @param string   $type    The type to hook into
     * @param callable $handler The handler to invoke
     */
    public function addPreResolutionHook(string $type, callable $handler): void
    {
        $this->preResolutionHooks[$type] = $handler;
    }

    /**
     * Adds a post-resolution hook for a specific type.
     *
     * @param string   $type    The type to hook into
     * @param callable $handler The handler to invoke
     */
    public function addPostResolutionHook(string $type, callable $handler): void
    {
        $this->postResolutionHooks[$type] = $handler;
    }

    /**
     * Resolves a service or class by checking singletons, providers, bindings, and hooks.
     *
     * @param string     $alias  The name of the service or class
     * @param array|null $params Optional parameters for instantiation
     *
     * @return mixed The resolved service or class instance
     * @throws Exception If the service or class cannot be resolved
     */
    public function resolve(string $alias, ?array $params = []): mixed
    {
        $descriptor = $this->container->getServiceDescriptor($alias);

        if (!$descriptor) {
            throw new \Exception("Service '$alias' not found.");
        }

        if ($instance = $descriptor->getResolvedInstance()) {
            return $instance;
        }

        if (!$instance = $this->handlePreResolutionHooks($descriptor, $params)) {
            $instance = $this->container->make($descriptor->getClassName(), $params);
        }

        $instance = $this->handlePostResolutionHooks($instance, $descriptor);

        if ($descriptor->isSingleton()) {
            $descriptor->setResolvedInstance($instance);
        }

        return $instance;
    }

    public function resolveOrMake(string $aliasOrClass, ?array $params = []): mixed
    {
        if (!$descriptor = $this->container->getServiceDescriptor($aliasOrClass)) {
            $instance = $this->container->make($aliasOrClass, $params);

            return $this->handlePostResolutionHooks($instance);
        }

        return $this->resolve($aliasOrClass, $params);
    }


    private function handlePreResolutionHooks(ServiceDescriptor $descriptor, ?array $params = []): mixed
    {
        $className = $descriptor->getClassName();

        foreach ($this->preResolutionHooks as $type => $handler) {
            if (is_subclass_of($className, $type) || $className === $type) {
                return $handler($descriptor, $params);
            }
        }

        return null;
    }

    private function handlePostResolutionHooks(object $instance, ?ServiceDescriptor $descriptor = null): mixed
    {
        foreach ($this->postResolutionHooks as $type => $handler) {
            // Ensure $type is a valid class name before comparing
            if (is_subclass_of($instance, $type) || get_class($instance) === $type) {
                // Call the handler if it's callable, else handle the string case
                if (is_callable($handler)) {
                    return $handler($instance, $descriptor);
                } else {
                    // Handle non-callable $handler logic (if necessary)
                    // If $handler is a string, perhaps resolve or log it, depending on your use case
                }
            }
        }

        return $instance;
    }
}
