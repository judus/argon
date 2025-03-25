<?php

declare(strict_types=1);

namespace Maduser\Argon\Container;

use Closure;
use Maduser\Argon\Container\Contracts\ServiceBinderInterface;
use Maduser\Argon\Container\Contracts\ServiceDescriptorInterface;
use Maduser\Argon\Container\Exceptions\ContainerException;

/**
 * Handles service registrations into the container.
 */
final class ServiceBinder implements ServiceBinderInterface
{
    /**
     * @var array<string, ServiceDescriptor>
     */
    private array $descriptors = [];

    /**
     * @return array<string, ServiceDescriptor>
     */
    public function getDescriptors(): array
    {
        return $this->descriptors;
    }

    public function getDescriptor(string $id): ?ServiceDescriptorInterface
    {
        return $this->descriptors[$id] ?? null;
    }

    public function has(string $id): bool
    {
        return isset($this->descriptors[$id]);
    }

    /**
     * Registers a singleton service.
     *
     * @param string $id
     * @param Closure|string|null $concrete
     * @throws ContainerException
     */
    public function singleton(string $id, Closure|string|null $concrete = null): void
    {
        $this->bind($id, $concrete, true);
    }

    /**
     * Registers a service (transient or singleton).
     *
     * @param string $id
     * @param Closure|string|null $concrete
     * @param bool $isSingleton
     * @throws ContainerException
     */
    public function bind(string $id, Closure|string|null $concrete = null, bool $isSingleton = false): void
    {
        $concrete ??= $id;

        if (!$concrete instanceof Closure && !class_exists($concrete)) {
            throw ContainerException::fromServiceId($id, "Class '$concrete' does not exist.");
        }

        $this->descriptors[$id] = new ServiceDescriptor($concrete, $isSingleton);
    }

    /**
     * Registers a factory service.
     *
     * @param string $id
     * @param callable(): mixed $factory
     * @param bool $singleton
     */
    public function registerFactory(string $id, callable $factory, bool $singleton = true): void
    {
        $this->descriptors[$id] = new ServiceDescriptor(
            static fn(): mixed => $factory(),
            $singleton
        );
    }
}
