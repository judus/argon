<?php

declare(strict_types=1);

namespace Maduser\Argon\Container;

use Closure;
use Maduser\Argon\Container\Exceptions\ContainerException;
use Maduser\Argon\Container\Exceptions\NotFoundException;
use ReflectionException;

trait ContextualBindingSupport
{
    private ContextualBindingRegistry $contextualBindings;

    public function for(string $target): ContextualBindingBuilder
    {
        return new ContextualBindingBuilder($this->contextualBindings, $target);
    }

    /**
     * @throws ReflectionException
     * @throws ContainerException
     * @throws NotFoundException
     */
    protected function resolveContextual(string $consumer, string $dependency): object
    {
        $override = $this->contextualBindings->get($consumer, $dependency);

        if ($override === null) {
            throw new NotFoundException("No contextual binding found for '$dependency' in '$consumer'");
        }

        if ($override instanceof Closure) {
            return $override();
        }

        return $this->get($override);
    }
}
