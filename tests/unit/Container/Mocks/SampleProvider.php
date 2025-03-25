<?php

declare(strict_types=1);

namespace Tests\Unit\Container\Mocks;

use Maduser\Argon\Container\Contracts\ServiceProviderInterface;
use Maduser\Argon\Container\ServiceContainer;

class SampleProvider implements ServiceProviderInterface
{
    public function register(ServiceContainer $container): void
    {
    }

    public function boot(ServiceContainer $container): void
    {
    }
}
