<?php

declare(strict_types=1);

namespace Maduser\Argon\Container\Contracts;

use Maduser\Argon\Container\ArgonContainer;

/**
 * Declares a service provider capable of registering and bootstrapping bindings.
 */
interface ServiceProviderInterface
{
    /**
     * Register bindings in the container.
     */
    public function register(ArgonContainer $container): void;

    /**
     * Optionally perform post-registration setup.
     */
    public function boot(ArgonContainer $container): void;
}
