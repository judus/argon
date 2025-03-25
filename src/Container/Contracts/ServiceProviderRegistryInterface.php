<?php

declare(strict_types=1);

namespace Maduser\Argon\Container\Contracts;

use Maduser\Argon\Container\Exceptions\ContainerException;
use Maduser\Argon\Container\Exceptions\NotFoundException;

/**
 * Registers and boots service providers via the container.
 */
interface ServiceProviderRegistryInterface
{
    /**
     * Registers a service provider and tags it.
     *
     * @param class-string<ServiceProviderInterface> $className
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function register(string $className): void;

    /**
     * Boots all registered service providers.
     */
    public function boot(): void;
}