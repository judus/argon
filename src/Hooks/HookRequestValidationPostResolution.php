<?php

namespace Maduser\Argon\Hooks;

use App\RequestValidation;
use Maduser\Argon\Container\ServiceContainer;
use Maduser\Argon\Container\ServiceDescriptor;

class HookRequestValidationPostResolution
{
    public function __construct(private ServiceContainer $container) {}

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
        $instance->validate();

        return $instance;
    }
}