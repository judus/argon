<?php

declare(strict_types=1);

namespace Maduser\Argon\Container;

use Closure;

readonly class ContextualBindingBuilder
{
    public function __construct(
        private ContextualBindings $registry,
        private string $target
    ) {
    }

    public function set(string $dependency, string|Closure $concrete): void
    {
        $this->registry->set($this->target, $dependency, $concrete);
    }
}
