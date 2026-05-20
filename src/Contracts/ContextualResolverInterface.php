<?php

declare(strict_types=1);

namespace Maduser\Argon\Container\Contracts;

use Maduser\Argon\Container\Exceptions\ContainerException;
use Maduser\Argon\Container\Exceptions\NotFoundException;

interface ContextualResolverInterface
{
    /**
     * @param class-string|string $target
     */
    public function for(string $target): ContextualBindingBuilderInterface;

    /**
     * @param class-string|string $consumer
     * @param class-string|string $dependency
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function resolve(string $consumer, string $dependency): object;

    /**
     * @param class-string|string $consumer
     * @param class-string|string $dependency
     */
    public function has(string $consumer, string $dependency): bool;
}
