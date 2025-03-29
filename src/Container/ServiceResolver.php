<?php

declare(strict_types=1);

namespace Maduser\Argon\Container;

use Closure;
use Maduser\Argon\Container\Contracts\InterceptorRegistryInterface;
use Maduser\Argon\Container\Contracts\ArgumentResolverInterface;
use Maduser\Argon\Container\Contracts\ReflectionCacheInterface;
use Maduser\Argon\Container\Contracts\ServiceBinderInterface;
use Maduser\Argon\Container\Contracts\ServiceResolverInterface;
use Maduser\Argon\Container\Exceptions\ContainerException;
use Maduser\Argon\Container\Exceptions\NotFoundException;
use ReflectionParameter;
use Throwable;

/**
 * Resolves services, handling recursive instantiation and singleton caching.
 */
final class ServiceResolver implements ServiceResolverInterface
{
    /**
     * Currently resolving IDs (to detect circular dependencies).
     *
     * @var array<string, true>
     */
    private array $resolving = [];

    public function __construct(
        private readonly ServiceBinderInterface $binder,
        private readonly ReflectionCacheInterface $reflectionCache,
        private readonly InterceptorRegistryInterface $interceptors,
        private readonly ArgumentResolverInterface $parameterResolver
    ) {
    }

    /**
     * Resolves a service by ID or class name.
     *
     * @template T of object
     * @param class-string<T>|string $id
     * @param array<array-key, mixed> $args
     * @return object
     * @psalm-return ($id is class-string<T> ? T : object)
     *
     * @throws ContainerException
     * @throws NotFoundException
     *
     * We're going to make one single exception here,
     * IMAO this more likely PossiblyPsalmsProblem
     * @psalm-suppress PossiblyUnusedReturnValue
     */
    public function resolve(string $id, array $args = []): object
    {
        $this->checkCircularDependency($id);

        // Pre resolution interceptors
        $result = $this->interceptors->matchPre($id, $args);
        if ($result !== null) {
            $this->removeFromResolving($id);

            return $result;
        }

        // Registered service
        $descriptor = $this->binder->getDescriptor($id);
        if ($descriptor) {
            if ($descriptor->isSingleton() && $instance = $descriptor->getInstance()) {
                $this->removeFromResolving($id);
                return $instance;
            }

            $concrete = $descriptor->getConcrete();
            $args = array_merge($descriptor->getArguments(), $args);

            $instance = $concrete instanceof Closure
                ? (object) $concrete()
                : $this->resolveClass($concrete, $args);

            $instance = $this->interceptors->matchPost($instance);

            if ($descriptor->isSingleton()) {
                $descriptor->storeInstance($instance);
            }

            $this->removeFromResolving($id);
            return $instance;
        }

        // Unregistered service (Direct class resolution)
        if (!class_exists($id)) {
            throw new NotFoundException($id);
        }

        $reflection = $this->reflectionCache->get($id);
        if (!$reflection->isInstantiable()) {
            throw ContainerException::forNonInstantiableClass($id, $reflection->getName());
        }

        $instance = $this->resolveClass($id, $args);
        $instance = $this->interceptors->matchPost($instance);

        $this->removeFromResolving($id);
        return $instance;
    }

    /**
     * Resolves a concrete class by analyzing its constructor dependencies.
     *
     * @template T of object
     * @param class-string<T> $className
     * @param array<array-key, mixed> $args
     * @return object
     * @psalm-return ($className is class-string<T> ? T : object)
     *
     * @throws ContainerException
     * @throws NotFoundException
     */
    private function resolveClass(string $className, array $args = []): mixed
    {
        if ($descriptor = $this->binder->getDescriptor($className)) {
            $concrete = $descriptor->getConcrete();

            if ($concrete instanceof Closure) {
                return (object) $concrete();
            }

            if ($concrete !== $className) {
                $args = array_merge($descriptor->getArguments(), $args);
                return $this->resolveClass($concrete, $args);
            }
        }

        $reflection = $this->reflectionCache->get($className);

        if ($reflection->isInterface()) {
            throw ContainerException::forUnresolvableDependency($className, 'Cannot instantiate interface');
        }

        if (!$reflection->isInstantiable()) {
            throw ContainerException::forNonInstantiableClass($className, $reflection->getName());
        }

        $constructor = $reflection->getConstructor();

        $dependencies = $constructor
            ? $this->resolveConstructorParameters($constructor->getParameters(), $args)
            : [];

        try {
            return $reflection->newInstanceArgs(array_values($dependencies));
        } catch (Throwable $e) {
            throw ContainerException::forInstantiationFailure($className, $e);
        }
    }

    /**
     * Resolves constructor parameters using contextual/primitive logic.
     *
     * @param array<int, ReflectionParameter> $params
     * @param array<array-key, mixed> $overrides
     * @return array<int, mixed>
     *
     * @throws ContainerException
     * @throws NotFoundException
     */
    private function resolveConstructorParameters(array $params, array $overrides): array
    {
        return array_map(
            fn(ReflectionParameter $param): mixed => $this->parameterResolver->resolve($param, $overrides),
            $params
        );
    }

    /**
     * Detects circular dependencies by tracking resolution chain.
     *
     * @param string $id
     * @throws ContainerException
     */
    private function checkCircularDependency(string $id): void
    {
        if (isset($this->resolving[$id])) {
            $chain = array_keys($this->resolving);
            $chain[] = $id;
            throw ContainerException::forCircularDependency($id, $chain);
        }

        $this->resolving[$id] = true;
    }

    /**
     * Removes a service ID from the current resolving stack.
     *
     * @param string $id
     */
    private function removeFromResolving(string $id): void
    {
        unset($this->resolving[$id]);
    }
}
