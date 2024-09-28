<?php

namespace Maduser\Argon\Hooks;

use Maduser\Argon\Container\ServiceContainer;
use Maduser\Argon\Container\ServiceDescriptor;
use Maduser\Argon\Container\ServiceProvider;

readonly class HookServiceProviderPostResolution
{
    public function __construct(private ServiceContainer $container)
    {
    }

    /**
     * Invoked when a ServiceProvider is registered.
     *
     * @param mixed                  $instance
     * @param ServiceDescriptor|null $descriptor
     *
     * @return mixed
     */
    public function __invoke(mixed $instance, ?ServiceDescriptor $descriptor = null): mixed
    {
        return $instance->resolve();
    }
}
