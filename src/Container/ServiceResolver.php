<?php

declare(strict_types=1);

namespace Maduser\Argon\Container;

use Closure;
use Maduser\Argon\Container\Contracts\InterceptorRegistryInterface;
use Maduser\Argon\Container\Contracts\ArgumentResolverInterface;
use Maduser\Argon\Container\Contracts\ReflectionCacheInterface;
use Maduser\Argon\Container\Contracts\ServiceBinderInterface;
use Maduser\Argon\Container\Contracts\ServiceDescriptorInterface;
use Maduser\Argon\Container\Contracts\ServiceResolverInterface;
use Maduser\Argon\Container\Exceptions\ContainerException;
use Maduser\Argon\Container\Exceptions\NotFoundException;
use ReflectionException;
use ReflectionMethod;
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
        private readonly ArgumentResolverInterface $argumentResolver
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

        $result = $this->interceptors->matchPre($id, $args);
        if ($result !== null) {
            $this->removeFromResolving($id);
            return $result;
        }

        $descriptor = $this->binder->getDescriptor($id);

        if ($descriptor !== null) {
            $instance = $this->resolveFromDescriptor($id, $descriptor, $args);
        } elseif (class_exists($id)) {
            $instance = $this->resolveUnregistered($id, $args);
        } else {
            throw new NotFoundException($id);
        }

        $this->removeFromResolving($id);

        return $instance;
    }

    /**
     * @throws ContainerException
     * @throws NotFoundException
     */
    private function resolveFromDescriptor(string $id, ServiceDescriptorInterface $descriptor, array $args): object
    {
        if ($descriptor->isShared() && $instance = $descriptor->getInstance()) {
            return $instance;
        }

        if ($descriptor->hasFactory()) {
            $instance = $this->resolveFromFactory($id, $descriptor, $args);
        } else {
            $concrete = $descriptor->getConcrete();
            $args = array_merge($descriptor->getArguments(), $args);

            $instance = $concrete instanceof Closure
                ? (object) $concrete()
                : $this->resolveClass($concrete, $args);
        }

        $instance = $this->interceptors->matchPost($instance);

        if ($descriptor->isShared()) {
            $descriptor->storeInstance($instance);
        }

        return $instance;
    }


    /**
     * @throws ContainerException
     * @throws NotFoundException
     * @throws ReflectionException
     */
    private function resolveFromFactory(string $id, ServiceDescriptorInterface $descriptor, array $args): object
    {
        $factoryClass = $descriptor->getFactoryClass();
        $method = $descriptor->getFactoryMethod();

        if ($factoryClass === null) {
            throw ContainerException::fromServiceId($id, 'Factory class not defined.');
        }

        $factoryInstance = $this->resolve($factoryClass, $args);

        if (!method_exists($factoryInstance, $method)) {
            throw new ContainerException(sprintf(
                'Factory method "%s" not found on class "%s".',
                $method,
                $factoryClass
            ));
        }

        $reflection = new ReflectionMethod($factoryInstance, $method);

        if ($reflection->isStatic()) {
            // Even though we resolved it, static methods don't need the instance
            $instance = (object) call_user_func_array([$factoryClass, $method], $args);
        } else {
            $instance = (object) $factoryInstance->$method(...$args);
        }

        return $instance;
    }

    /**
     * Resolves a class that is not registered in the container.
     *
     * @param class-string $id
     *
     * @throws ContainerException
     * @throws NotFoundException
     */
    private function resolveUnregistered(string $id, array $args): object
    {
        $reflection = $this->reflectionCache->get($id);

        if (!$reflection->isInstantiable()) {
            throw ContainerException::forNonInstantiableClass($id, $reflection->getName());
        }

        $instance = $this->resolveClass($id, $args);

        return $this->interceptors->matchPost($instance);
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
            fn(ReflectionParameter $param): mixed => $this->argumentResolver->resolve($param, $overrides),
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
