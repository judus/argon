<?php

declare(strict_types=1);

namespace Maduser\Argon\Container\Contracts;

use Closure;

/**
 * Stores contextual bindings between consumers and their dependencies.
 */
interface ContextualBindingsInterface
{
    /**
     * @param string $consumer
     * @param string $dependency
     * @param string|Closure $concrete
     * @return void
     */
    public function bind(string $consumer, string $dependency, string|Closure $concrete): void;

    /**
     * @param string $consumer
     * @param string $dependency
     * @return string|Closure|null
     */
    public function get(string $consumer, string $dependency): string|Closure|null;

    /**
     * @param string $consumer
     * @param string $dependency
     * @return bool
     */
    public function has(string $consumer, string $dependency): bool;

    public function getBindings(): array;
}
