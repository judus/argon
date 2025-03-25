<?php

declare(strict_types=1);

namespace Maduser\Argon\Container\Contracts;

use Maduser\Argon\Container\ContextualBindingBuilder;
use Maduser\Argon\Container\Exceptions\ContainerException;
use Maduser\Argon\Container\Exceptions\NotFoundException;

interface ContextualResolverInterface
{
    public function for(string $target): ContextualBindingBuilder;

    /**
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function resolve(string $consumer, string $dependency): object;

    public function has(string $consumer, string $dependency): bool;
}
