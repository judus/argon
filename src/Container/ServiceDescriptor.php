<?php

namespace Maduser\Argon\Container;

class ServiceDescriptor
{
    private string $name;
    private string $className;
    private bool $isSingleton;
    private ?array $defaultParams;
    private ?object $resolvedInstance = null;

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

    public function getName(): string
    {
        return $this->name;
    }

    public function getClassName(): string
    {
        return $this->className;
    }

    public function isSingleton(): bool
    {
        return $this->isSingleton;
    }

    public function getDefaultParams(): ?array
    {
        return $this->defaultParams;
    }

    public function getResolvedInstance(): ?object
    {
        return $this->resolvedInstance;
    }

    public function setResolvedInstance(object $instance): void
    {
        $this->resolvedInstance = $instance;
    }
}
