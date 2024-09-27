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

    private array $reflectionCache = [];

    /**
     * @var array Pre-resolution hooks
     */
    private array $preResolutionHooks = [];

    /**
     * @var array Post-resolution hooks
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

    private function handlePostResolutionHooks(mixed $instance, ?ServiceDescriptor $descriptor = null ): mixed
    {
        foreach ($this->postResolutionHooks as $type => $handler) {
            if (is_subclass_of($instance, $type) || $instance === $type) {
                return $handler($instance, $descriptor);
            }
        }

        return $instance;
    }

    /**
     * Checks if a service is registered as a singleton or container.
     *
     * @param string $alias The name of the service or class
     *
     * @return mixed|null The registered singleton or container if available, null otherwise
     */
    public function registered(string $alias): mixed
    {
        return $this->container->findProvider($alias);
    }

    /**
     * Checks if a container is registered for a given name.
     *
     * @param string $alias
     *
     * @return bool True if the container exists, false otherwise
     */
    public function has(string $alias): bool
    {
        return $this->container->hasProvider($alias);
    }
}
