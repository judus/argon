<?php

declare(strict_types=1);

namespace Maduser\Argon\Container;

use Closure;
use Maduser\Argon\Container\Contracts\BindingBuilderInterface;
use Maduser\Argon\Container\Contracts\ServiceBinderInterface;
use Maduser\Argon\Container\Contracts\ServiceDescriptorInterface;
use Maduser\Argon\Container\Contracts\TagManagerInterface;
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

    public function __construct(
        private readonly TagManagerInterface $tagManager,
        private bool $defaultShared = true
    ) {
    }

    /**
     * @return array<string, ServiceDescriptor>
     */
    #[\Override]
    public function getDescriptors(): array
    {
        return $this->descriptors;
    }

    #[\Override]
    public function getDescriptor(string $id): ?ServiceDescriptorInterface
    {
        return $this->descriptors[$id] ?? null;
    }

    #[\Override]
    public function has(string $id): bool
    {
        return isset($this->descriptors[$id]);
    }

    #[\Override]
    public function setDefaultShared(bool $shared): void
    {
        $this->defaultShared = $shared;
    }

    /**
     * Registers a singleton service.
     *
     * @param string $id
     * @param Closure|string|null $concrete
     * @param array<array-key, mixed>|null $args
     * @return BindingBuilderInterface
     * @throws ContainerException
     */
    #[\Override]
    public function set(
        string $id,
        Closure|string|null $concrete = null,
        ?array $args = null
    ): BindingBuilderInterface {
        $concrete ??= $id;

        if (!$concrete instanceof Closure && !class_exists($concrete)) {
            throw ContainerException::fromServiceId($id, "Class '$concrete' does not exist.");
        }

        $descriptor = new ServiceDescriptor($id, $concrete, $this->defaultShared, $args);
        $this->descriptors[$id] = $descriptor;

        return new BindingBuilder($descriptor, $this->tagManager);
    }

    /**
     * Registers a factory service.
     *
     * @param string $id
     * @param callable(): mixed $factory
     * @param bool $shared
     */
    #[\Override]
    public function registerFactory(string $id, callable $factory, bool $shared = true): void
    {
        $this->descriptors[$id] = new ServiceDescriptor(
            $id,
            static fn(): mixed => $factory(),
            $shared
        );
    }
}
