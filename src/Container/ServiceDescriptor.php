<?php

declare(strict_types=1);

namespace Maduser\Argon\Container;

class ServiceDescriptor
{
    private string $className;
    private bool $isSingleton;


    private ?object $resolvedInstance = null;
    private string $name;
    private ?array $defaultParams;
    private ?ServiceContainer $container;

    public function __construct(
        string $name,
        string $className,
        bool $isSingleton = false,
        ?array $defaultParams = []
    ) {
        $this->name = $name;
        $this->className = $className;
        $this->isSingleton = $isSingleton;
        $this->defaultParams = $defaultParams;
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

    public function getDefaultParams(): ?array
    {
        return $this->defaultParams;
    }
}
