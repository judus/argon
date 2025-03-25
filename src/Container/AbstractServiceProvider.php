<?php

declare(strict_types=1);

namespace Maduser\Argon\Container;

use Maduser\Argon\Container\Contracts\ServiceProviderInterface;

/**
 * Base class for service providers.
 *
 * Allows registration of bindings and optional bootstrapping logic.
 */
abstract class AbstractServiceProvider implements ServiceProviderInterface
{
    /**
     * Register services in the container.
     */
    abstract public function register(ServiceContainer $container): void;

    /**
     * Optionally bootstrap services after registration.
     */
    public function boot(ServiceContainer $container): void
    {
        // Optional, override if needed
    }
}
