<?php

declare(strict_types=1);

namespace Maduser\Argon\Container;

use Closure;
use Maduser\Argon\Container\Contracts\ContextualBindingsInterface;
use Override;

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
     * @return array
     */
    #[Override]
    public function getBindings(): array
    {
        return $this->bindings;
    }

    /**
     * @param string $consumer
     * @param string $dependency
     * @param string|Closure $concrete
     * @return void
     */
    #[Override]
    public function bind(string $consumer, string $dependency, string|Closure $concrete): void
    {
        $this->bindings[$consumer][$dependency] = $concrete;
    }

    /**
     * @param string $consumer
     * @param string $dependency
     * @return string|Closure|null
     */
    #[Override]
    public function get(string $consumer, string $dependency): string|Closure|null
    {
        return $this->bindings[$consumer][$dependency] ?? null;
    }

    /**
     * @param string $consumer
     * @param string $dependency
     * @return bool
     */
    #[Override]
    public function has(string $consumer, string $dependency): bool
    {
        return isset($this->bindings[$consumer][$dependency]);
    }
}
