<?php

namespace Tests\Mocks;

use Maduser\Argon\Container\Contracts\ServiceProviderInterface;
use Maduser\Argon\Container\Exceptions\ContainerException;
use Maduser\Argon\Container\ServiceContainer;
use stdClass;

class DummyProvider implements ServiceProviderInterface
{
    public static bool $booted = false;

    /**
     * @throws ContainerException
     */
    public function register(ServiceContainer $container): void
    {
        $container->singleton('dummy.service', stdClass::class);
    }

    public function boot(ServiceContainer $container): void
    {
        self::$booted = true;
    }
}
