<?php

declare(strict_types=1);

namespace Maduser\Argon\Container;

use Closure;
use Maduser\Argon\Container\Exceptions\ContainerException;

/**
 * Handles registration of services into the container.
 */
class ServiceBinder
{
    /**
     * @param array<string, ServiceDescriptor> $bindings Reference to the container's service map
     */
    private array $descriptors = [];

    public function getDescriptors(): array
    {
        return $this->descriptors;
    }

    public function getDescriptor(string $id): ?ServiceDescriptor
    {
        return $this->descriptors[$id] ?? null;
    }

    public function has(string $id): bool
    {
        return isset($this->descriptors[$id]);
    }

    /**
     * Register a singleton service.
     *
     * @param string $id
     * @param Closure|string|null $concrete
     * @throws ContainerException
     */
    public function singleton(string $id, Closure|string|null $concrete = null): void
    {
        $this->bind($id, $concrete ?? $id, true);
    }

    /**
     * Register a service (transient or singleton).
     *
     * @param string $id
     * @param Closure|string|null $concrete
     * @param bool $isSingleton
     * @throws ContainerException
     */
    public function bind(string $id, Closure|string|null $concrete = null, bool $isSingleton = false): void
    {
        $concrete ??= $id;

        if (!($concrete instanceof Closure) && !class_exists($concrete)) {
            throw ContainerException::fromServiceId($id, "Class '$concrete' does not exist.");
        }

        $this->descriptors[$id] = new ServiceDescriptor($concrete, $isSingleton);
    }

    /**
     * Register a factory callable.
     *
     * @param string $id
     * @param callable $factory
     * @param bool $singleton
     */
    public function registerFactory(string $id, callable $factory, bool $singleton = true): void
    {
        $factoryClosure = fn(): mixed => call_user_func($factory);
        $this->descriptors[$id] = new ServiceDescriptor($factoryClosure, $singleton);
    }
}
