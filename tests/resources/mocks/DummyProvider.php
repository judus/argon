<?php

declare(strict_types=1);

namespace Tests\Mocks;

use Maduser\Argon\Container\Contracts\ServiceProviderInterface;
use Maduser\Argon\Container\Exceptions\ContainerException;
use Maduser\Argon\Container\ArgonContainer;
use stdClass;

final class DummyProvider implements ServiceProviderInterface
{
    public static bool $booted = false;

    /**
     * @throws ContainerException
     */
    #[\Override]
    public function register(ArgonContainer $container): void
    {
        $container->set('dummy.service', stdClass::class);
    }

    #[\Override]
    public function boot(ArgonContainer $container): void
    {
        self::$booted = true;
    }
}
