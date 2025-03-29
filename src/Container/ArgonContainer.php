<?php

declare(strict_types=1);

namespace Maduser\Argon\Container;

use Closure;
use Maduser\Argon\Container\Contracts\ArgumentMapInterface;
use Maduser\Argon\Container\Contracts\ArgumentResolverInterface;
use Maduser\Argon\Container\Contracts\ContextualBindingBuilderInterface;
use Maduser\Argon\Container\Contracts\ContextualBindingsInterface;
use Maduser\Argon\Container\Contracts\ContextualResolverInterface;
use Maduser\Argon\Container\Contracts\InterceptorRegistryInterface;
use Maduser\Argon\Container\Contracts\ParameterStoreInterface;
use Maduser\Argon\Container\Contracts\PostResolutionInterceptorInterface;
use Maduser\Argon\Container\Contracts\PreResolutionInterceptorInterface;
use Maduser\Argon\Container\Contracts\ReflectionCacheInterface;
use Maduser\Argon\Container\Contracts\ServiceBinderInterface;
use Maduser\Argon\Container\Contracts\ServiceProviderInterface;
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
class ArgonContainer implements ContainerInterface
{
    private readonly TagManagerInterface $tags;
    private readonly CallableInvoker $invoker;
    private readonly ContextualBindingsInterface $contextualBindings;
    private readonly ContextualResolverInterface $contextual;
    private readonly ServiceProviderRegistryInterface $providers;
    private readonly ServiceResolverInterface $serviceResolver;
    private readonly ArgumentResolverInterface $argumentResolver;
    private readonly ServiceBinderInterface $binder;
    private readonly ArgumentMapInterface $arguments;
    private readonly ParameterStoreInterface $parameterStore;

    public function __construct(
        ?ArgumentMapInterface $arguments = null,
        ?ParameterStoreInterface $parameters = null,
        private readonly ReflectionCacheInterface $reflectionCache = new ReflectionCache(),
        private readonly InterceptorRegistryInterface $interceptors = new InterceptorRegistry(),
        ?TagManagerInterface $tags = null,
        ?CallableInvoker $invoker = null,
        ?ContextualBindingsInterface $contextualRegistry = null,
        ?ContextualResolverInterface $contextual = null,
        ?ServiceProviderRegistryInterface $providers = null,
        ?ServiceResolverInterface $serviceResolver = null,
        ?ArgumentResolverInterface $argumentResolver = null,
        ?ServiceBinderInterface $binder = null,
    ) {
        $this->arguments = $arguments ?? new ArgumentMap();
        $this->parameterStore = $parameters ?? new ParameterStore();
        $this->contextualBindings = $contextualRegistry ?? new ContextualBindings();
        $this->contextual = $contextual ?? new ContextualResolver($this, $this->contextualBindings);
        $this->tags = $tags ?? new TagManager($this);
        $this->providers = $providers ?? new ServiceProviderRegistry($this);
        $this->binder = $binder ?? new ServiceBinder();

        $this->argumentResolver = $argumentResolver ?? new ArgumentResolver(
            $this->contextual,
            $this->arguments,
            $this->contextualBindings
        );

        $this->serviceResolver = $serviceResolver ?? new ServiceResolver(
            $this->binder,
            $this->reflectionCache,
            $this->interceptors,
            $this->argumentResolver
        );

        $this->argumentResolver->setServiceResolver($this->serviceResolver);

        $this->invoker = $invoker ?? new CallableInvoker(
            $this->serviceResolver,
            $this->argumentResolver
        );
    }

    public function getArgumentMap(): ArgumentMapInterface
    {
        return $this->arguments;
    }

    public function getContextualBindings(): ContextualBindingsInterface
    {
        return $this->contextualBindings;
    }

    /**
     * @template TGet of object
     * @psalm-param class-string<TGet>|string $id
     * @psalm-return ($id is class-string<TGet> ? TGet : object)
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function get(string $id, array $args = []): object
    {
        return $this->serviceResolver->resolve($id, $args);
    }

    public function has(string $id): bool
    {
        return $this->binder->has($id);
    }

    /**
     * @throws ContainerException
     */
    public function bind(
        string $id,
        Closure|string|null $concrete = null,
        bool $isSingleton = false,
        ?array $args = null
    ): ArgonContainer {

        if ($args !== null) {
            $this->arguments->set($id, $args);
        }

        $this->binder->bind($id, $concrete, $isSingleton);

        return $this;
    }

    /**
     * @throws ContainerException
     */
    public function singleton(string $id, Closure|string|null $concrete = null, ?array $args = null): ArgonContainer
    {
        if ($args !== null) {
            $this->arguments->set($id, $args);
        }

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

    public function getParameters(): ParameterStoreInterface
    {
        return $this->parameterStore;
    }

    public function registerFactory(string $id, callable $factory, bool $isSingleton = true): ArgonContainer
    {
        $this->binder->registerFactory($id, $factory, $isSingleton);

        return $this;
    }

    /**
     * @param class-string<ServiceProviderInterface> $className
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function registerProvider(string $className): ArgonContainer
    {
        $this->providers->register($className);

        return $this;
    }

    /**
     * Registers an interceptor (pre- or post-resolution).
     *
     * @param class-string $interceptorClass
     * @throws ContainerException
     */
    public function registerInterceptor(string $interceptorClass): ArgonContainer
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
     * @return array<class-string<PostResolutionInterceptorInterface>>
     */
    public function getPostInterceptors(): array
    {
        return $this->interceptors->allPost();
    }

    /**
     * @return array<class-string<PreResolutionInterceptorInterface>>
     */
    public function getPreInterceptors(): array
    {
        return $this->interceptors->allPre();
    }

    /**
     * @param string $id
     * @param list<string> $tags
     * @return ArgonContainer
     */
    public function tag(string $id, array $tags): ArgonContainer
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

    /**
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function boot(): ArgonContainer
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
     * @return ArgonContainer
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function extend(string $id, callable $decorator): ArgonContainer
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
