<?php

declare(strict_types=1);

namespace Maduser\Argon\Container\Contracts;

use Closure;

/**
 * Facilitates defining contextual bindings for a specific target.
 */
interface ContextualBindingBuilderInterface
{
    /**
     * Registers a contextual binding for a given dependency.
     *
     * @param string $dependency The dependency identifier (usually interface or class name).
     * @param string|Closure $concrete The concrete implementation or a Closure factory.
     * @return void
     */
    public function set(string $dependency, string|Closure $concrete): void;
}
