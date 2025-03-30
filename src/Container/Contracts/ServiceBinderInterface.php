<?php

declare(strict_types=1);

namespace Maduser\Argon\Container\Contracts;

use Closure;
use Maduser\Argon\Container\BindingBuilder;
use Maduser\Argon\Container\Exceptions\ContainerException;
use Maduser\Argon\Container\ServiceDescriptor;

/**
 * Handles service registrations into the container.
 */
interface ServiceBinderInterface
{
    /**
     * @return array<string, ServiceDescriptor>
     */
    public function getDescriptors(): array;

    public function getDescriptor(string $id): ?ServiceDescriptorInterface;

    public function has(string $id): bool;

    /**
     * Registers a singleton service.
     *
     * @param string $id
     * @param Closure|string|null $concrete
     * @throws ContainerException
     */
    public function singleton(string $id, Closure|string|null $concrete = null): BindingBuilderInterface;

    /**
     * Registers a service (transient or singleton).
     *
     * @param string $id
     * @param Closure|string|null $concrete
     * @param bool $isSingleton
     * @throws ContainerException
     */
    public function bind(string $id, Closure|string|null $concrete = null, bool $isSingleton = false): BindingBuilderInterface;

    /**
     * Registers a runtime-only closure-based factory.
     * Not compatible with container compilation.
     *
     * @param string $id
     * @param callable(): mixed $factory
     * @param bool $singleton
     */
    public function registerFactory(string $id, callable $factory, bool $singleton = true): void;
}
