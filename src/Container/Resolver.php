<?php

declare(strict_types=1);

namespace Maduser\Argon\Container;

use Closure;
use Exception;
use Maduser\Argon\Container\Exceptions\ContainerException;
use Maduser\Argon\Container\Exceptions\ServiceNotFoundException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use ReflectionException;

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
     * Resolver constructor.
     *
     * @param ServiceContainer $container The service container instance
     */
    public function __construct(ServiceContainer $container)
    {
        $this->container = $container;
    }

    /**
     * Resolves a service or class by its alias or creates a new instance if not found.
     *
     * @param string|Closure $aliasOrClass The service alias or class to resolve
     * @param array|null     $params       Optional parameters for instantiation
     *
     * @return mixed The resolved or instantiated service
     *
     * @throws ContainerExceptionInterface
     */
    public function resolveOrMake(string|Closure $aliasOrClass, ?array $params = [], ?string $requester = null): mixed
    {
        try {
            if ($aliasOrClass instanceof Closure) {
                $resolvedClass = $this->container->execute($aliasOrClass, $params, $requester);

                if (is_string($resolvedClass)) {
                    return $this->container->resolveOrMake($resolvedClass, $params, $requester);
                }

                return $resolvedClass;
            }

            $descriptor = $this->container->getServiceDescriptor($aliasOrClass);

            if (!$descriptor) {
                $instance = $this->container->make($aliasOrClass, $params);

                return $this->container->handlePostResolutionHooks($instance);
            }

            return $this->resolveFromDescriptor($descriptor, $params);

        } catch (ReflectionException $e) {
            throw new ContainerException(
                "Error resolving or making service '$aliasOrClass': " . $e->getMessage(), $e
            );
        } catch (Exception $e) {
            throw new ContainerException(
                "General error resolving or making service '$aliasOrClass': " . $e->getMessage(), $e
            );
        }
    }

    /**
     * Resolves a service or class from a service descriptor.
     *
     * @param ServiceDescriptor $descriptor The service descriptor
     * @param array|null        $params     Optional parameters for instantiation
     *
     * @return mixed The resolved service or class instance
     * @throws ContainerExceptionInterface If the service cannot be resolved
     */
    private function resolveFromDescriptor(ServiceDescriptor $descriptor, ?array $params = []): mixed
    {
        try {
            // If the service is already resolved (singleton), return the instance
            if ($instance = $descriptor->getInstance()) {
                return $instance;
            }

            // Handle pre-resolution hooks, if any
            if (!$instance = $this->container->handlePreResolutionHooks($descriptor, $params)) {
                // Check if the definition is a closure and invoke it, otherwise instantiate the class
                $definition = $descriptor->getDefinition();
                if ($definition instanceof Closure) {
                    $instance = $this->container->execute($definition);
                } else {
                    $instance = $this->container->make($definition, $params);
                }
            }

            // Apply post-resolution hooks
            $instance = $this->container->handlePostResolutionHooks($instance, $descriptor);

            // Cache the instance if it's a singleton
            if ($descriptor->isSingleton()) {
                $descriptor->setInstance($instance);
            }

            return $instance;
        } catch (ReflectionException $e) {
            throw new ContainerException("Error resolving service from descriptor: " . $e->getMessage(), $e);
        }
    }

    /**
     * Resolves a service by its alias.
     *
     * @param string     $alias  The name of the service or class
     * @param array|null $params Optional parameters for instantiation
     *
     * @return mixed The resolved service or class instance
     * @throws NotFoundExceptionInterface If the service is not found
     * @throws ContainerExceptionInterface If there is an error during resolution
     */
    public function resolve(string $alias, ?array $params = []): mixed
    {
        try {
            $descriptor = $this->container->getServiceDescriptor($alias);

            if (!$descriptor) {
                throw new ServiceNotFoundException($alias);
            }

            return $this->resolveFromDescriptor($descriptor, $params);
        } catch (ServiceNotFoundException $e) {
            throw $e; // Let the not found exception bubble up as-is
        } catch (ReflectionException $e) {
            throw new ContainerException("Error resolving service '$alias': " . $e->getMessage(), $e);
        }
    }
}
