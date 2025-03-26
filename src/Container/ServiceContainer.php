<?php

declare(strict_types=1);

namespace Maduser\Argon\Container;

use Closure;
use Maduser\Argon\Container\Contracts\ContextualBindingBuilderInterface;
use Maduser\Argon\Container\Contracts\ContextualBindingsInterface;
use Maduser\Argon\Container\Contracts\ContextualResolverInterface;
use Maduser\Argon\Container\Contracts\InterceptorRegistryInterface;
use Maduser\Argon\Container\Contracts\ParameterRegistryInterface;
use Maduser\Argon\Container\Contracts\ParameterResolverInterface;
use Maduser\Argon\Container\Contracts\PostResolutionInterceptorInterface;
use Maduser\Argon\Container\Contracts\PreResolutionInterceptorInterface;
use Maduser\Argon\Container\Contracts\ReflectionCacheInterface;
use Maduser\Argon\Container\Contracts\ServiceBinderInterface;
use Maduser\Argon\Container\Contracts\ServiceProviderInterface;
use Maduser\Argon\Container\Contracts\InterceptorInterface;
use Maduser\Argon\Container\Contracts\ServiceProviderRegistryInterface;
use Maduser\Argon\Container\Contracts\ServiceResolverInterface;
use Maduser\Argon\Container\Contracts\TagManagerInterface;
use Maduser\Argon\Container\Exceptions\ContainerException;
use Maduser\Argon\Container\Exceptions\NotFoundException;
use Maduser\Argon\Container\Support\NullServiceProxy;
use Psr\Container\ContainerInterface;

/**
 * Base container implementation.
 *
 * This class may be extended by generated or compiled containers.
 * Do not subclass this manually.
 *
 * @template T of object
 */
class ServiceContainer implements ContainerInterface
{
    private readonly TagManagerInterface $tags;
    private readonly CallableInvoker $invoker;
    private readonly ContextualBindingsInterface $contextualBindings;
    private readonly ContextualResolverInterface $contextual;
    private readonly ServiceProviderRegistryInterface $providers;
    private readonly ServiceResolverInterface $serviceResolver;
    private readonly ParameterResolverInterface $parameterResolver;
    private readonly ServiceBinderInterface $binder;

    public function __construct(
        private readonly ParameterRegistryInterface $parameters = new ParameterRegistry(),
        private readonly ReflectionCacheInterface $reflectionCache = new ReflectionCache(),
        private readonly InterceptorRegistryInterface $interceptors = new InterceptorRegistry(),
        ?TagManagerInterface $tags = null,
        ?CallableInvoker $invoker = null,
        ?ContextualBindingsInterface $contextualRegistry = null,
        ?ContextualResolverInterface $contextual = null,
        ?ServiceProviderRegistryInterface $providers = null,
        ?ServiceResolverInterface $serviceResolver = null,
        ?ParameterResolverInterface $parameterResolver = null,
        ?ServiceBinderInterface $binder = null,
    ) {
        $this->contextualBindings = $contextualRegistry ?? new ContextualBindings();
        $this->contextual = $contextual ?? new ContextualResolver($this, $this->contextualBindings);
        $this->tags = $tags ?? new TagManager($this);
        $this->providers = $providers ?? new ServiceProviderRegistry($this);
        $this->binder = $binder ?? new ServiceBinder();

        $this->parameterResolver = $parameterResolver ?? new ParameterResolver(
            $this->contextual,
            $this->parameters,
            $this->contextualBindings
        );

        $this->serviceResolver = $serviceResolver ?? new ServiceResolver(
            $this->binder,
            $this->reflectionCache,
            $this->interceptors,
            $this->parameterResolver
        );

        $this->parameterResolver->setServiceResolver($this->serviceResolver);

        $this->invoker = $invoker ?? new CallableInvoker(
            $this->serviceResolver,
            $this->parameterResolver
        );
    }

    /**
     * @template TGet of object
     * @param class-string<TGet>|string $id
     * @return object
     * @psalm-return ($id is class-string<TGet> ? TGet : object)
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function get(string $id): object
    {
        return $this->serviceResolver->resolve($id);
    }

    public function has(string $id): bool
    {
        return $this->binder->has($id);
    }

    /**
     * @throws ContainerException
     */
    public function bind(string $id, Closure|string|null $concrete = null, bool $isSingleton = false): ServiceContainer
    {
        $this->binder->bind($id, $concrete, $isSingleton);

        return $this;
    }

    /**
     * @throws ContainerException
     */
    public function singleton(string $id, Closure|string|null $concrete = null): ServiceContainer
    {
        $this->binder->singleton($id, $concrete);

        return $this;
    }

    /**
     * @return array<string, ServiceDescriptor>
     */
    public function getBindings(): array
    {
        return $this->binder->getDescriptors();
    }

    public function getParameters(): ParameterRegistryInterface
    {
        return $this->parameters;
    }

    public function registerFactory(string $id, callable $factory, bool $isSingleton = true): ServiceContainer
    {
        $this->binder->registerFactory($id, $factory, $isSingleton);

        return $this;
    }

    /**
     * @param class-string<ServiceProviderInterface> $className
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function registerProvider(string $className): ServiceContainer
    {
        $this->providers->register($className);

        return $this;
    }

    /**
     * Registers an interceptor (pre- or post-resolution).
     *
     * @param class-string<InterceptorInterface> $interceptorClass
     * @throws ContainerException
     */
    public function registerInterceptor(string $interceptorClass): ServiceContainer
    {
        if (!class_exists($interceptorClass)) {
            throw ContainerException::fromServiceId(
                $interceptorClass,
                "Interceptor class '$interceptorClass' does not exist."
            );
        }

        if (is_subclass_of($interceptorClass, PreResolutionInterceptorInterface::class)) {
            $this->interceptors->registerPre($interceptorClass);
        }

        if (is_subclass_of($interceptorClass, PostResolutionInterceptorInterface::class)) {
            $this->interceptors->registerPost($interceptorClass);
        }

        return $this;
    }

    /**
     * @return array<class-string<InterceptorInterface>>
     */
    public function getInterceptors(): array
    {
        return $this->interceptors->allPost();
    }

    /**
     * @param string $id
     * @param list<string> $tags
     * @return ServiceContainer
     */
    public function tag(string $id, array $tags): ServiceContainer
    {
        $this->tags->tag($id, $tags);

        return $this;
    }

    /**
     * @return array<string, list<string>>
     */
    public function getTags(): array
    {
        return $this->tags->all();
    }

    /**
     * @param string $tag
     * @return list<object>
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function getTagged(string $tag): array
    {
        return $this->tags->getTagged($tag);
    }

    public function isResolvable(string $id): bool
    {
        return $this->binder->has($id) || class_exists($id);
    }

    public function boot(): ServiceContainer
    {
        $this->providers->boot();

        return $this;
    }

    /**
     * Extends an already-resolved service at runtime.
     *
     * This method only works after the service has been resolved.
     * It should be called during `boot()` or runtime setup — not `register()`.
     *
     * @param string $id
     * @param callable(object):object $decorator
     * @return ServiceContainer
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function extend(string $id, callable $decorator): ServiceContainer
    {
        $instance = $this->get($id);
        $decorated = $decorator($instance);

        $this->binder->singleton($id, fn() => $decorated);
        $this->binder->getDescriptor($id)?->storeInstance($decorated);

        return $this;
    }

    public function for(string $target): ContextualBindingBuilderInterface
    {
        return $this->contextual->for($target);
    }

    /**
     * @param object|string $classOrCallable
     * @param string|null $method
     * @param array<string, mixed> $parameters
     * @return mixed
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function invoke(object|string $classOrCallable, ?string $method = null, array $parameters = []): mixed
    {
        return $this->invoker->call($classOrCallable, $method, $parameters);
    }

    /**
     * @param string $id
     * @return object
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function optional(string $id): object
    {
        return $this->has($id)
            ? $this->get($id)
            : new NullServiceProxy();
    }
}
