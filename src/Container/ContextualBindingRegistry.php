<?php

namespace Maduser\Argon\Container;

use Closure;

class ContextualBindingRegistry
{
    private array $bindings = [];

    public function set(string $consumer, string $dependency, string|Closure $concrete): void
    {
        $this->bindings[$consumer][$dependency] = $concrete;
    }

    public function get(string $consumer, string $dependency): string|Closure|null
    {
        return $this->bindings[$consumer][$dependency] ?? null;
    }

    public function has(string $consumer, string $dependency): bool
    {
        return isset($this->bindings[$consumer][$dependency]);
    }
}
