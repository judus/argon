<?php

declare(strict_types=1);

namespace Maduser\Argon\Container;

use Closure;
use Maduser\Argon\Container\Contracts\ArgumentMapInterface;
use Maduser\Argon\Container\Contracts\BindingBuilderInterface;
use Maduser\Argon\Container\Contracts\ContextualBindingBuilderInterface;
use Maduser\Argon\Container\Contracts\ContextualBindingsInterface;
use Maduser\Argon\Container\Contracts\ParameterStoreInterface;
use Maduser\Argon\Container\Contracts\PostResolutionInterceptorInterface;
use Maduser\Argon\Container\Contracts\PreResolutionInterceptorInterface;
use Maduser\Argon\Container\Contracts\ServiceProviderInterface;
use Maduser\Argon\Container\Exceptions\ContainerException;
use Maduser\Argon\Container\Exceptions\NotFoundException;

/**
 * Static proxy for the Argon service container.
 *
 * This class allows simple, global access to core container operations.
 * It is primarily intended for convenience in small apps or bootstrapping contexts.
 */
class Container
{
    private static ?ArgonContainer $instance = null;

    /**
     * Sets the current container instance to use statically.
     */
    public static function set(ArgonContainer $container): void
    {
        self::$instance = $container;
    }

    /**
     * Gets the current container instance, or creates one if none is set.
     */
    public static function instance(): ArgonContainer
    {
        return self::$instance ??= new ArgonContainer();
    }

    /**
     * Checks if a binding with the given ID exists in the container.
     */
    public static function has(string $id): bool
    {
        return self::instance()->has($id);
    }

    /**
     * Checks if the container can resolve the given ID, whether registered or autoloadable.
     */
    public static function isResolvable(string $id): bool
    {
        return self::instance()->isResolvable($id);
    }

    /**
     * Registers a service in the container.
     *
     * By default, this creates a transient (non-singleton) binding.
     *
     * @throws ContainerException
     */
    public static function bind(
        string $id,
        Closure|string|null $concrete = null,
        bool $singleton = false
    ): BindingBuilderInterface {
        return self::instance()->bind($id, $concrete, $singleton);
    }

    /**
     * Registers a singleton service in the container.
     *
     * The same instance will be reused on each resolution.
     *
     * @throws ContainerException
     */
    public static function singleton(string $id, Closure|string|null $concrete = null): BindingBuilderInterface
    {
        return self::instance()->singleton($id, $concrete);
    }

    /**
     * Registers a factory function that builds the service instance.
     *
     * Optionally makes it a singleton.
     *
     * @param string $id
     * @param callable(): mixed $factory
     * @param bool $singleton
     */
    public static function registerFactory(string $id, callable $factory, bool $singleton = true): void
    {
        self::instance()->registerFactory($id, $factory, $singleton);
    }

    /**
     * Resolves a service by its ID.
     *
     * @throws NotFoundException|ContainerException
     */
    public static function get(string $id): object
    {
        return self::instance()->get($id);
    }

    /**
     * Resolves a service if it exists, or returns a null-safe proxy otherwise.
     *
     * Useful for optional dependencies.
     *
     * @throws NotFoundException|ContainerException
     */
    public static function optional(string $id): object
    {
        return self::instance()->optional($id);
    }

    /**
     * Calls a method or closure with automatic dependency injection.
     *
     * @param object|string $target  Object instance or class name
     * @param string|null $method    Method name if calling on a class/object
     * @param array<string, mixed> $parameters      Optional parameter overrides
     *
     * @throws NotFoundException|ContainerException
     */
    public static function invoke(object|string $target, ?string $method = null, array $parameters = []): mixed
    {
        return self::instance()->invoke($target, $method, $parameters);
    }

    /**
     * Registers a service provider class.
     *
     * The provider is resolved and its `register()` method is called.
     *
     * @param class-string<ServiceProviderInterface> $class
     * @throws NotFoundException|ContainerException
     */
    public static function registerProvider(string $class): void
    {
        self::instance()->register($class);
    }

    /**
     * Calls the `boot()` method on all registered service providers.
     *
     * @throws NotFoundException|ContainerException
     */
    public static function boot(): void
    {
        self::instance()->boot();
    }

    /**
     * Applies a decorator or wrapper to an existing service.
     *
     * @param callable(object):object $decorator
     * @throws ContainerException|NotFoundException
     */
    public static function extend(string $id, callable $decorator): void
    {
        self::instance()->extend($id, $decorator);
    }

    /**
     * Registers a type interceptor.
     *
     * Interceptors can modify resolved services that match specific criteria.
     *
     * @param class-string $class
     * @throws ContainerException
     */
    public static function registerInterceptor(string $class): void
    {
        self::instance()->registerInterceptor($class);
    }

    /**
     * Tags a service with one or more tag names for grouped resolution.
     *
     * @param string $id
     * @param list<string> $tags
     */
    public static function tag(string $id, array $tags): void
    {
        self::instance()->tag($id, $tags);
    }

    /**
     * Gets all services associated with a given tag.
     *
     * @return list<object>
     * @throws NotFoundException|ContainerException
     */
    public static function tagged(string $tag): array
    {
        return self::instance()->getTagged($tag);
    }

    /**
     * Begins a contextual binding chain for a specific class.
     *
     * This allows overriding dependencies when resolving that class.
     */
    public static function for(string $target): ContextualBindingBuilderInterface
    {
        return self::instance()->for($target);
    }

    /**
     * Returns all registered service bindings.
     *
     * Useful for debugging or container inspection.
     *
     * @return array<string, ServiceDescriptor>
     */
    public static function bindings(): array
    {
        return self::instance()->getBindings();
    }

    /**
     * Returns a list of all registered interceptors.
     *
     * @return array<class-string<PreResolutionInterceptorInterface>>
     */
    public static function preInterceptors(): array
    {
        return self::instance()->getPreInterceptors();
    }

    /**
     * Returns a list of all registered interceptors.
     *
     * @return array<class-string<PostResolutionInterceptorInterface>>
     */
    public static function postInterceptors(): array
    {
        return self::instance()->getPostInterceptors();
    }

    /**
     * Returns a list of all tags and their associated service IDs.
     *
     * @return array<string, list<string>>
     */
    public static function tags(): array
    {
        return self::instance()->getTags();
    }

    /**
     * Gets access to the parameter store.
     */
    public static function parameters(): ParameterStoreInterface
    {
        return self::instance()->getParameters();
    }

    /**
     * Gets access to the argument map.
     */
    public static function arguments(): ArgumentMapInterface
    {
        return self::instance()->getArgumentMap();
    }

    /**
     * Gets access to the contextual binding registry.
     */
    public static function contextualBindings(): ContextualBindingsInterface
    {
        return self::instance()->getContextualBindings();
    }
}
