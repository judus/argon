<?php

declare(strict_types=1);

namespace Maduser\Argon\Hooks;

use Maduser\Argon\Container\ServiceDescriptor;

/**
 * @psalm-immutable
 */
class HookServiceProviderSetter
{
    private $container;

    public function __construct($container)
    {
        $this->container = $container;
    }

    public function __invoke(ServiceDescriptor $descriptor, string $alias)
    {
        $instance = $this->container->make($descriptor->getClassName());
        $instance->register();

        return $instance;
    }
}
