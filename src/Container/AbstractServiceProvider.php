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
    public function register(ArgonContainer $container): void
    {
        // Optional, override if needed
    }

    /**
     * Optionally bootstrap services after registration.
     */
    public function boot(ArgonContainer $container): void
    {
        // Optional, override if needed
    }
}
