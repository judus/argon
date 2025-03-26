<?php

declare(strict_types=1);

namespace Maduser\Argon\Container;

use Closure;
use Maduser\Argon\Container\Contracts\ServiceDescriptorInterface;

/**
 * Holds metadata about a service: its concrete implementation, singleton status,
 * and optional constructor arguments.
 */
final class ServiceDescriptor implements ServiceDescriptorInterface
{
    /**
     * @var class-string|Closure
     */
    private string|Closure $concrete;

    private bool $isSingleton;

    private ?object $instance = null;

    /**
     * @var array<string, mixed>
     */
    private array $arguments;

    /**
     * @param class-string|Closure $concrete
     * @param bool $isSingleton
     * @param array<string, mixed> $arguments
     */
    public function __construct(string|Closure $concrete, bool $isSingleton, array $arguments = [])
    {
        $this->concrete = $concrete;
        $this->isSingleton = $isSingleton;
        $this->arguments = $arguments;
    }

    public function isSingleton(): bool
    {
        return $this->isSingleton;
    }

    /**
     * @return class-string|Closure
     */
    public function getConcrete(): string|Closure
    {
        return $this->concrete;
    }

    public function getInstance(): ?object
    {
        return $this->instance;
    }

    public function storeInstance(object $instance): void
    {
        if ($this->isSingleton && $this->instance === null) {
            $this->instance = $instance;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function getArguments(): array
    {
        return $this->arguments;
    }
}
