<?php

declare(strict_types=1);

namespace Maduser\Argon\Container;

use Closure;
use Maduser\Argon\Container\Exceptions\ContainerException;
use Maduser\Argon\Container\Exceptions\NotFoundException;
use ReflectionClass;
use ReflectionException;
use ReflectionParameter;

class ServiceResolver
{
    /**
     * @var array<string, ServiceDescriptor>
     */
    private array $descriptors = [];

    /** @var array<string, true> */
    private array $resolving = [];

    public function __construct(
        private readonly ServiceBinder $binder,
        private readonly ReflectionCache $reflectionCache,
        private readonly InterceptorRegistry $interceptors,
        private readonly ParameterResolver $parameterResolver
    ) {
    }

    /**
     * @template T of object
     * @param class-string<T>|string $id
     * @param array $parameters
     * @return object
     * @psalm-return ($id is class-string<T> ? T : object)
     * @throws ContainerException
     * @throws NotFoundException
     * @throws ReflectionException
     */
    public function resolve(string $id, array $parameters = []): object
    {
        $this->checkCircularDependency($id);

        $descriptor = $this->binder->getDescriptor($id);

        if (!$descriptor) {
            if (!class_exists($id)) {
                throw NotFoundException::forService($id);
            }

            $reflection = $this->reflectionCache->get($id);
            if (!$reflection->isInstantiable()) {
                throw ContainerException::forNonInstantiableClass($id, $reflection->getName());
            }

            $instance = $this->resolveClass($id, $parameters);
            return $this->interceptors->apply($instance);
        }

        $instance = $descriptor->getInstance();
        $concrete = $descriptor->getConcrete();

        if ($descriptor->isSingleton() && $instance !== null) {
            $this->removeFromResolving($id);
            return $instance;
        }

        $instance = $concrete instanceof Closure
            ? $concrete()
            : $this->resolveClass($concrete, $parameters);

        $instance = $this->interceptors->apply($instance);

        if ($descriptor->isSingleton()) {
            $descriptor->storeInstance($instance);
        }

        $this->removeFromResolving($id);
        return $instance;
    }

    /**
     * @template T of object
     * @param class-string<T> $className
     * @param array $parameters
     * @return object
     * @throws ContainerException
     * @throws NotFoundException
     * @throws ReflectionException
     */
    private function resolveClass(string $className, array $parameters = []): object
    {
        $descriptor = $this->binder->getDescriptor($className);
        if ($descriptor) {
            $concrete = $descriptor->getConcrete();

            if ($concrete instanceof Closure) {
                return $concrete();
            }

            if ($concrete !== $className) {
                return $this->resolveClass($concrete, $parameters);
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
        if (is_null($constructor)) {
            return new $className();
        }

        $dependencies = array_map(
            fn(ReflectionParameter $param): mixed => $this->parameterResolver->resolve($param, $parameters),
            $constructor->getParameters()
        );

        return $reflection->newInstanceArgs($dependencies);
    }

    private function checkCircularDependency(string $id): void
    {
        if (isset($this->resolving[$id])) {
            $chain = array_keys($this->resolving);
            $chain[] = $id;
            throw ContainerException::forCircularDependency($id, $chain);
        }

        $this->resolving[$id] = true;
    }

    private function removeFromResolving(string $id): void
    {
        unset($this->resolving[$id]);
    }
}
