<?php

declare(strict_types=1);

namespace Maduser\Argon\Container;

use Closure;

class ServiceDescriptor
{
    private string|Closure $definition;
    private bool $isSingleton;
    private ?object $instance = null;
    private string $name;
    private ?array $defaultParams;

    public function __construct(
        string $name,
        string|Closure $definition,
        bool $isSingleton = false,
        ?array $defaultParams = []
    ) {
        $this->name = $name;
        $this->definition = $definition;
        $this->isSingleton = $isSingleton;
        $this->defaultParams = $defaultParams;
    }

    public function getInstance(): ?object
    {
        return $this->instance;
    }

    public function setInstance(?object $instance): void
    {
        $this->instance = $instance;
    }

    public function isSingleton(): bool
    {
        return $this->isSingleton;
    }

    public function getDefinition(): string|Closure
    {
        return $this->definition;
    }

    /**
     * Checks if the service is defined as a Closure.
     */
    public function isClosure(): bool
    {
        return $this->definition instanceof Closure;
    }

    /**
     * If the service is not a closure, return the class name.
     */
    public function getClassName(): ?string
    {
        return is_string($this->definition) ? $this->definition : null;
    }

    public function getDefaultParams(): ?array
    {
        return $this->defaultParams;
    }
}
