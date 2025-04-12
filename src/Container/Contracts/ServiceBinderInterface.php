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
     * @param array|null $args
     * @return BindingBuilderInterface
     * @throws ContainerException
     */
    public function set(
        string $id,
        Closure|string|null $concrete = null,
        ?array $args = null
    ): BindingBuilderInterface;

    /**
     * Registers a runtime-only closure-based factory.
     * Not compatible with container compilation.
     *
     * @param string $id
     * @param callable(): mixed $factory
     * @param bool $shared
     */
    public function registerFactory(string $id, callable $factory, bool $shared = true): void;
}
