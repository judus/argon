<?php

declare(strict_types=1);

namespace Maduser\Argon\Container;

use Maduser\Argon\Container\Contracts\ServiceProviderInterface;

abstract class ServiceProvider implements ServiceProviderInterface
{
    /**
     * Registers services in the container.
     * This method must be implemented by all concrete service providers.
     *
     * @param ServiceContainer $container
     */
    abstract public function register(ServiceContainer $container): void;

    /**
     * Bootstraps additional setup after registration.
     * Can be overridden by subclasses if needed.
     *
     * @param ServiceContainer $container
     */
    public function boot(ServiceContainer $container): void
    {
        // Optional boot logic, can be left empty
    }
}
