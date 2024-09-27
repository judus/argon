<?php

declare(strict_types=1);

namespace Maduser\Argon\Hooks;

use Maduser\Argon\Container\ServiceContainer;
use Maduser\Argon\Container\ServiceDescriptor;
use ReflectionException;

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