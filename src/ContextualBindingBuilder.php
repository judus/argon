<?php

declare(strict_types=1);

namespace Maduser\Argon\Container;

use Closure;
use Maduser\Argon\Container\Contracts\ContextualBindingBuilderInterface;
use Maduser\Argon\Container\Contracts\ContextualBindingsInterface;
use Override;

/**
 * Facilitates defining contextual bindings for a specific target.
 */
final readonly class ContextualBindingBuilder implements ContextualBindingBuilderInterface
{
    public function __construct(
        private ContextualBindingsInterface $registry,
        private string $target
    ) {
    }

    /**
     * Registers a contextual binding for a given dependency.
     *
     * @param string $dependency The dependency identifier (usually interface or class name).
     * @param string|Closure $concrete The concrete implementation or a Closure factory.
     * @return void
     */
    #[Override]
    public function set(string $dependency, string|Closure $concrete): void
    {
        $this->registry->bind($this->target, $dependency, $concrete);
    }
}
