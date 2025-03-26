<?php

declare(strict_types=1);

namespace Maduser\Argon\Container;

use Closure;
use Maduser\Argon\Container\Contracts\ContextualBindingBuilderInterface;
use Maduser\Argon\Container\Contracts\ContextualBindingsInterface;
use Maduser\Argon\Container\Contracts\ContextualResolverInterface;
use Maduser\Argon\Container\Exceptions\ContainerException;
use Maduser\Argon\Container\Exceptions\NotFoundException;
use ReflectionException;

/**
 * Resolves contextual bindings or falls back to container resolution.
 */
final readonly class ContextualResolver implements ContextualResolverInterface
{
    public function __construct(
        private ServiceContainer $container,
        private ContextualBindingsInterface $registry
    ) {
    }

    /**
     * Creates a contextual binding builder for the given target.
     *
     * @param string $target
     * @return ContextualBindingBuilderInterface
     */
    public function for(string $target): ContextualBindingBuilderInterface
    {
        return new ContextualBindingBuilder($this->registry, $target);
    }

    /**
     * Resolves a contextual override or throws if not found.
     *
     * @param string $consumer
     * @param string $dependency
     * @return object
     *
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

    /**
     * Checks if a contextual override exists for the given consumer and dependency.
     *
     * @param string $consumer
     * @param string $dependency
     * @return bool
     */
    public function has(string $consumer, string $dependency): bool
    {
        return $this->registry->has($consumer, $dependency);
    }
}
