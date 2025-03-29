<?php

declare(strict_types=1);

namespace Tests\Mocks;

use Maduser\Argon\Container\Contracts\ServiceProviderInterface;
use Maduser\Argon\Container\Exceptions\ContainerException;
use Maduser\Argon\Container\ArgonContainer;
use stdClass;

class DummyProvider implements ServiceProviderInterface
{
    public static bool $booted = false;

    /**
     * @throws ContainerException
     */
    public function register(ArgonContainer $container): void
    {
        $container->singleton('dummy.service', stdClass::class);
    }

    public function boot(ArgonContainer $container): void
    {
        self::$booted = true;
    }
}
