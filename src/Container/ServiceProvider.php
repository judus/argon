<?php

declare(strict_types=1);

namespace Maduser\Argon\Container;

use Maduser\Argon\Container\Contracts\ServiceProviderInterface;

abstract class ServiceProvider implements ServiceProviderInterface
{
    /**
     * Registers services in the container.
     *
     * @param ServiceContainer $container
     */
    abstract public function register(ServiceContainer $container): void;

    /**
     * Bootstraps additional setup after registration.
     *
     * @param ServiceContainer $container
     */
    public function boot(ServiceContainer $container): void
    {
        // Optional boot logic, can be left empty
    }
}
