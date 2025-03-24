<?php

declare(strict_types=1);

namespace Maduser\Argon\Container;

use Closure;
use Maduser\Argon\Container\Exceptions\ContainerException;
use Maduser\Argon\Container\Exceptions\NotFoundException;
use ReflectionException;

readonly class ContextualResolver
{
    public function __construct(
        private ServiceContainer $container,
        private ContextualBindings $registry
    ) {
    }

    public function for(string $target): ContextualBindingBuilder
    {
        return new ContextualBindingBuilder($this->registry, $target);
    }

    /**
     * Resolves a contextual override or throws if not found.
     *
     * @throws ReflectionException
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function resolve(string $consumer, string $dependency): object
    {
        $override = $this->registry->get($consumer, $dependency);

        if ($override === null) {
            throw new NotFoundException("No contextual binding found for '$dependency' in '$consumer'");
        }

        if ($override instanceof Closure) {
            return $override();
        }

        return $this->container->get($override);
    }

    public function has(string $consumer, string $dependency): bool
    {
        return $this->registry->has($consumer, $dependency);
    }
}
