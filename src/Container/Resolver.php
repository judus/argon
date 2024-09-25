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
     * @var Provider The service provider managing bindings, singletons, and providers
     */
    private Provider $provider;

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
     * @param Provider $provider The service provider instance
     */
    public function __construct(Provider $provider)
    {
        $this->provider = $provider;
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
     * @param string     $serviceName The name of the service or class
     * @param array|null $params      Optional parameters for instantiation
     *
     * @return mixed The resolved service or class instance
     * @throws Exception If the service or class cannot be resolved
     */
    public function resolve(string $serviceName, ?array $params = []): mixed
    {
        $instance = null;

        // Get the registered provider or singleton, or throw an exception if not found
        $registeredService = $this->provider->getProvider($serviceName);

        // Handle pre-resolution hooks
        $instance = $this->handlePreResolutionHooks($registeredService, $params);

        // If no pre-hooks resolved the instance, let's create it now
        if (!$instance && is_string($registeredService)) {
            // Only call make if the registeredService is a string (class name)
            $instance = $this->provider->make($registeredService, $params);
        }

        // If $registeredService is already an object, we don't need to call make()
        if (is_object($registeredService)) {
            $instance = $registeredService;
        }

        // Handle post-resolution hooks
        return $this->handlePostResolutionHooks($instance);
    }

    private function handlePreResolutionHooks(mixed $registeredService, ?array $params = []): mixed
    {
        if (is_object($registeredService)) {
            return null;  // Skip pre-hooks if it's already an instance
        }

        foreach ($this->preResolutionHooks as $type => $handler) {
            if (is_subclass_of($registeredService, $type) || $registeredService === $type) {
                $instance = $handler($registeredService, $params);
                if ($instance !== null) {
                    return $instance;
                }
            }
        }

        return null;
    }

    private function handlePostResolutionHooks(mixed $instance): mixed
    {
        foreach ($this->postResolutionHooks as $type => $handler) {
            if (is_subclass_of($instance, $type) || $instance === $type) {
                return $handler($instance);
            }
        }

        return $instance;
    }
    /**
     * Checks if a service is registered as a singleton or provider.
     *
     * @param string $name The name of the service or class
     *
     * @return mixed|null The registered singleton or provider if available, null otherwise
     */
    public function registered(string $name): mixed
    {
        return $this->provider->findProvider($name);
    }

    /**
     * Checks if a provider is registered for a given name.
     *
     * @param string $name The name of the service or provider
     *
     * @return bool True if the provider exists, false otherwise
     */
    public function has(string $name): bool
    {
        return $this->provider->hasProvider($name);
    }
}
