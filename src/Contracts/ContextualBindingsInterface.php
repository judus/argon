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
     * @param class-string|string $consumer
     * @param class-string|string $dependency
     * @param string|Closure $concrete
     * @return void
     */
    public function bind(string $consumer, string $dependency, string|Closure $concrete): void;

    /**
     * @param class-string|string $consumer
     * @param class-string|string $dependency
     * @return string|Closure|null
     */
    public function get(string $consumer, string $dependency): string|Closure|null;

    /**
     * @param class-string|string $consumer
     * @param class-string|string $dependency
     * @return bool
     */
    public function has(string $consumer, string $dependency): bool;

    /**
     * @return array<string, array<string, string|Closure>>
     */
    public function getBindings(): array;
}
