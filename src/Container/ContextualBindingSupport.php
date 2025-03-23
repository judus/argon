<?php

namespace Maduser\Argon\Container;

use Closure;

trait ContextualBindingSupport
{
    private ContextualBindingRegistry $contextualBindings;

    public function for(string $target): ContextualBindingBuilder
    {
        return new ContextualBindingBuilder($this->contextualBindings, $target);
    }

    protected function resolveContextual(string $consumer, string $dependency): object
    {
        $override = $this->contextualBindings->get($consumer, $dependency);
        if ($override instanceof Closure) {
            return $override();
        }
        return $this->get($override);
    }
}
