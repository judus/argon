<?php

namespace Maduser\Argon\Container;

use Closure;

class ConditionalBinding
{
    private string $requesterClass;
    private ServiceContainer $container;
    private array $bindings = [];
    private string $currentInterface;

    public function __construct(string $requesterClass, ServiceContainer $container)
    {
        $this->requesterClass = $requesterClass;
        $this->container = $container;
    }

    public function requires(string $interface): self
    {
        $this->currentInterface = $interface;

        return $this;
    }

    public function give(string|Closure $implementation): self
    {
        $this->bindings[$this->currentInterface] = $implementation;

        return $this;
    }

    public function appliesTo(?string $requester): bool
    {
        return $requester === $this->requesterClass;
    }

    public function resolve(string $interface)
    {
        return $this->container->resolveOrMake($this->bindings[$interface]);
    }
}