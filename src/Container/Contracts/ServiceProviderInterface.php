<?php

declare(strict_types=1);

namespace Maduser\Argon\Container\Contracts;

use Maduser\Argon\Container\ServiceContainer;

interface ServiceProviderInterface
{
    /**
     * Registers services within the container.
     *
     * @param ServiceContainer $container
     * @return void
     */
    public function register(ServiceContainer $container): void;

    /**
     * Bootstraps any additional setup after registration.
     *
     * @param ServiceContainer $container
     * @return void
     */
    public function boot(ServiceContainer $container): void;
}
