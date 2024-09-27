<?php

namespace Maduser\Argon\Container;

class ServiceDescriptor
{
    private string $name;
    private string $className;
    private bool $isSingleton;
    private ?array $defaultParams;
    private ?ServiceContainer $container;
    private ?object $resolvedInstance = null;

    public function __construct(
        string $name,
        string $className,
        bool $isSingleton = false,
        ?array $defaultParams = [],
        ?ServiceContainer $container = null
    ) {
        $this->name = $name;
        $this->className = $className;
        $this->isSingleton = $isSingleton;
        $this->defaultParams = $defaultParams;
        $this->container = $container;
    }

    // Return the resolved instance, or resolve if not yet instantiated
    public function getInstance(): mixed
    {
        // Check if we already have an instance
        if ($this->resolvedInstance !== null) {
            return $this->resolvedInstance;
        }

        // Resolve the instance if not already done
        $instance = $this->container->resolve($this->name);

        // Cache the instance if it's a singleton
        if ($this->isSingleton) {
            $this->resolvedInstance = $instance;
        }

        return $instance;
    }

    public function getResolvedInstance(): ?object
    {
        return $this->resolvedInstance;
    }

    public function setResolvedInstance(?object $instance): void
    {
        $this->resolvedInstance = $instance;
    }

    public function isSingleton(): bool
    {
        return $this->isSingleton;
    }

    public function getClassName(): string
    {
        return $this->className;
    }
}

