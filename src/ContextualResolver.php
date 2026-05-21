<?php

declare(strict_types=1);

namespace Maduser\Argon\Container;

use Closure;
use Maduser\Argon\Container\Contracts\ContextualBindingBuilderInterface;
use Maduser\Argon\Container\Contracts\ContextualBindingsInterface;
use Maduser\Argon\Container\Contracts\ContextualResolverInterface;
use Maduser\Argon\Container\Exceptions\ContainerException;
use Maduser\Argon\Container\Exceptions\NotFoundException;
use Override;

/**
 * Resolves contextual bindings or falls back to container resolution.
 */
final readonly class ContextualResolver implements ContextualResolverInterface
{
    public function __construct(
        private ArgonContainer $container,
        private ContextualBindingsInterface $bindings
    ) {
    }

    /**
     * Creates a contextual binding builder for the given target.
     *
     * @param class-string|string $target
     * @return ContextualBindingBuilderInterface
     */
    #[Override]
    public function for(string $target): ContextualBindingBuilderInterface
    {
        return new ContextualBindingBuilder($this->bindings, $target);
    }

    /**
     * Resolves a contextual override or throws if not found.
     *
     * @param class-string|string $consumer
     * @param class-string|string $dependency
     * @return object
     *
     * @throws ContainerException
     * @throws NotFoundException
     */
    #[Override]
    public function resolve(string $consumer, string $dependency): object
    {
        $override = $this->bindings->get($consumer, $dependency);

        if ($override === null) {
            throw new NotFoundException("No contextual binding found for '$dependency' in '$consumer'");
        }

        if ($override instanceof Closure) {
            return $this->ensureObjectResult($consumer, $dependency, $this->container->invoke($override));
        }

        return $this->container->get($override);
    }

    /**
     * Checks if a contextual override exists for the given consumer and dependency.
     *
     * @param class-string|string $consumer
     * @param class-string|string $dependency
     * @return bool
     */
    #[Override]
    public function has(string $consumer, string $dependency): bool
    {
        return $this->bindings->has($consumer, $dependency);
    }

    private function ensureObjectResult(string $consumer, string $dependency, mixed $value): object
    {
        if (!is_object($value)) {
            throw ContainerException::fromServiceId(
                $dependency,
                sprintf(
                    'Contextual closure binding for "%s" must return an object, got %s.',
                    $consumer,
                    get_debug_type($value)
                )
            );
        }

        return $value;
    }
}
