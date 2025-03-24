<?php

namespace Tests\Mocks;

use Maduser\Argon\Container\Contracts\ServiceProviderInterface;
use Maduser\Argon\Container\Exceptions\ContainerException;
use Maduser\Argon\Container\ServiceContainer;
use stdClass;

class DummyProvider implements ServiceProviderInterface
{
    /**
     * @throws ContainerException
     */
    public function register(ServiceContainer $container): void
    {
        $container->singleton('dummy.service', stdClass::class);
    }

    public function boot(ServiceContainer $container): void
    {
        // Not tested here, but valid to call after container is built
    }
}
