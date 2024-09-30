<?php

namespace Maduser\Argon\Container\Hooks;

use Maduser\Argon\Container\ServiceContainer;
use Maduser\Argon\Container\ServiceDescriptor;

/**
 * @psalm-immutable
 */
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
     *
     * @psalm-pure
     */
    public function __invoke(mixed $instance, ?ServiceDescriptor $descriptor = null): mixed
    {
        return $instance->resolve();
    }
}
