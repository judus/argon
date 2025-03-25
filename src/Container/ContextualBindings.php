<?php

declare(strict_types=1);

namespace Maduser\Argon\Container;

use Closure;
use Maduser\Argon\Container\Contracts\ContextualBindingsInterface;

/**
 * Stores contextual bindings between consumers and their dependencies.
 */
final class ContextualBindings implements ContextualBindingsInterface
{
    /**
     * @var array<string, array<string, string|Closure>>
     */
    private array $bindings = [];

    /**
     * @param string $consumer
     * @param string $dependency
     * @param string|Closure $concrete
     * @return void
     */
    public function set(string $consumer, string $dependency, string|Closure $concrete): void
    {
        $this->bindings[$consumer][$dependency] = $concrete;
    }

    /**
     * @param string $consumer
     * @param string $dependency
     * @return string|Closure|null
     */
    public function get(string $consumer, string $dependency): string|Closure|null
    {
        return $this->bindings[$consumer][$dependency] ?? null;
    }

    /**
     * @param string $consumer
     * @param string $dependency
     * @return bool
     */
    public function has(string $consumer, string $dependency): bool
    {
        return isset($this->bindings[$consumer][$dependency]);
    }
}
