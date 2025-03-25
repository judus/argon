<?php

declare(strict_types=1);

namespace Maduser\Argon\Container\Contracts;

use Maduser\Argon\Container\ServiceContainer;

/**
 * Declares a service provider capable of registering and bootstrapping bindings.
 */
interface ServiceProviderInterface
{
    /**
     * Register bindings in the container.
     */
    public function register(ServiceContainer $container): void;

    /**
     * Optionally perform post-registration setup.
     */
    public function boot(ServiceContainer $container): void;
}
