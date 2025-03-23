<?php

namespace Maduser\Argon\Container;

use Closure;

readonly class ContextualBindingBuilder
{
    public function __construct(
        private ContextualBindingRegistry $registry,
        private string $target
    ) {
    }

    public function set(string $dependency, string|Closure $concrete): void
    {
        $this->registry->set($this->target, $dependency, $concrete);
    }
}
