<?php

declare(strict_types=1);

namespace Maduser\Argon\Container;

use Closure;
use Maduser\Argon\Container\Contracts\ServiceProviderInterface;
use Psr\Container\ContainerInterface;
use ReflectionException;
use Maduser\Argon\Container\Contracts\TypeInterceptorInterface;
use Maduser\Argon\Container\Exceptions\ContainerException;
use Maduser\Argon\Container\Exceptions\NotFoundException;
use Maduser\Argon\Container\Support\NullServiceProxy;

/**
 * Class ServiceContainer
 *
 * A PSR-11 compliant, modular dependency injection container.
 * Responsibilities are delegated to specialized components.
 *
 * @template T of object
 */
class ServiceContainer implements ContainerInterface
{
    // Core dependencies delegated to internal subsystems
    private readonly TagManager $tags;
    private readonly CallableInvoker $invoker;
    private readonly ContextualBindings $contextualBindings;
    private readonly ContextualResolver $contextual;
    private readonly ServiceProviderRegistry $providers;
    private readonly ServiceResolver $serviceResolver;
    private readonly ParameterResolver $parameterResolver;
    private readonly ServiceBinder $binder;

    /**
     * @param ParameterRegistry $parameters Global parameter override registry.
     * @param ReflectionCache $reflectionCache Shared reflection cache.
     * @param InterceptorRegistry $interceptors Type interceptor registry.
     * @param TagManager|null $tags Tag manager instance.
     * @param CallableInvoker|null $invoker Callable/method injector.
     * @param ContextualBindings|null $contextualRegistry Per-consumer contextual bindings.
     * @param ContextualResolver|null $contextual Resolver for contextual dependencies.
     * @param ServiceProviderRegistry|null $providers Service provider registry and boot logic.
     * @param ServiceResolver|null $serviceResolver Core resolution engine.
     * @param ParameterResolver|null $parameterResolver Argument/parameter resolver.
     * @param ServiceBinder|null $binder Binding registry and factory manager.
     */
    public function __construct(
        private readonly ParameterRegistry $parameters = new ParameterRegistry(),
        private readonly ReflectionCache $reflectionCache = new ReflectionCache(),
        private readonly InterceptorRegistry $interceptors = new InterceptorRegistry(),
        ?TagManager $tags = null,
        ?CallableInvoker $invoker = null,
        ?ContextualBindings $contextualRegistry = null,
        ?ContextualResolver $contextual = null,
        ?ServiceProviderRegistry $providers = null,
        ?ServiceResolver $serviceResolver = null,
        ?ParameterResolver $parameterResolver = null,
        ?ServiceBinder $binder = null,
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
     * Begin a contextual binding chain for a specific class.
     *
     * @param string $target Class that will receive the contextual override.
     * @return ContextualBindingBuilder
     */
    public function for(string $target): ContextualBindingBuilder
    {
        return $this->contextual->for($target);
    }

    /**
     * Resolve a dependency from the container.
     *
     * @template TGet of object
     * @param class-string<TGet>|string $id
     * @return object
     * @psalm-return ($id is class-string<TGet> ? TGet : object)
     *
     * @throws ContainerException
     * @throws NotFoundException
     * @throws ReflectionException
     */
    public function get(string $id): object
    {
        return $this->serviceResolver->resolve($id);
    }

    /**
     * Determine if a service is explicitly registered.
     *
     * @param class-string<T>|string $id
     * @return bool
     */
    public function has(string $id): bool
    {
        return $this->binder->has($id);
    }

    /**
     * Determine if a service can be resolved (registered or class exists).
     *
     * @param class-string<T>|string $id
     * @return bool
     */
    public function canResolve(string $id): bool
    {
        return $this->binder->has($id) || class_exists($id);
    }

    /**
     * Register a singleton binding.
     *
     * @param class-string<T>|string $id
     * @param Closure|string|null $concrete
     * @throws ContainerException
     */
    public function singleton(string $id, Closure|string|null $concrete = null): void
    {
        $this->binder->singleton($id, $concrete);
    }

    /**
     * Register a transient binding or override.
     *
     * @param class-string<T>|string $id
     * @param Closure|string|null $concrete
     * @param bool $isSingleton
     * @throws ContainerException
     */
    public function bind(string $id, Closure|string|null $concrete = null, bool $isSingleton = false): void
    {
        $this->binder->bind($id, $concrete, $isSingleton);
    }

    /**
     * Register a factory closure or callable for a service.
     *
     * @param string $id
     * @param callable $factory
     * @param bool $isSingleton
     */
    public function registerFactory(string $id, callable $factory, bool $isSingleton = true): void
    {
        $this->binder->registerFactory($id, $factory, $isSingleton);
    }

    /**
     * Register a service provider class.
     *
     * @param class-string<ServiceProviderInterface> $className
     * @throws ContainerException
     * @throws NotFoundException
     * @throws ReflectionException
     */
    public function registerServiceProvider(string $className): void
    {
        $this->providers->register($className);
    }

    /**
     * Boot all loaded service providers.
     *
     * @throws ContainerException
     * @throws NotFoundException
     * @throws ReflectionException
     */
    public function bootServiceProviders(): void
    {
        $this->providers->boot();
    }

    /**
     * Register a type-level interceptor.
     *
     * @param class-string<TypeInterceptorInterface> $interceptorClass
     * @throws ContainerException
     */
    public function registerInterceptor(string $interceptorClass): void
    {
        $this->interceptors->register($interceptorClass);
    }

    /**
     * Add one or more tags to a service binding.
     *
     * @param string $id
     * @param array<string> $tags
     */
    public function tag(string $id, array $tags): void
    {
        $this->tags->tag($id, $tags);
    }

    /**
     * Retrieve all services associated with a tag.
     *
     * @param string $tag
     * @return array<object>
     * @throws ContainerException
     * @throws NotFoundException
     * @throws ReflectionException
     */
    public function getTagged(string $tag): array
    {
        return $this->tags->getTagged($tag);
    }

    /**
     * Get a map of all tags and associated services.
     *
     * @return array<string, array<string>>
     */
    public function getTags(): array
    {
        return $this->tags->all();
    }

    /**
     * Return the raw registered service descriptors.
     *
     * @return array<string, ServiceDescriptor>
     */
    public function getServices(): array
    {
        return $this->binder->getDescriptors();
    }

    /**
     * Get all registered type interceptor class names.
     *
     * @return array<class-string<TypeInterceptorInterface>>
     */
    public function getInterceptors(): array
    {
        return $this->interceptors->all();
    }

    /**
     * Get the global parameter override registry.
     *
     * @return ParameterRegistry
     */
    public function getParameters(): ParameterRegistry
    {
        return $this->parameters;
    }

    /**
     * Resolve and invoke a method or closure with autowired parameters.
     *
     * @param object|string $classOrCallable
     * @param string|null $method
     * @param array $parameters
     * @return mixed
     * @throws ContainerException
     * @throws NotFoundException
     * @throws ReflectionException
     */
    public function call(object|string $classOrCallable, ?string $method = null, array $parameters = []): mixed
    {
        return $this->invoker->call($classOrCallable, $method, $parameters);
    }

    /**
     * Attempt to retrieve a service, or return a NullServiceProxy if unavailable.
     *
     * @param class-string<T>|string $id
     * @return object
     * @psalm-return T|NullServiceProxy
     * @throws ContainerException
     * @throws NotFoundException
     * @throws ReflectionException
     */
    public function if(string $id): object
    {
        return $this->has($id)
            ? $this->get($id)
            : new NullServiceProxy();
    }
}
