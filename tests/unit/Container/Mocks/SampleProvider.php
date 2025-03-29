<?php

declare(strict_types=1);

namespace Tests\Unit\Container\Mocks;

use Maduser\Argon\Container\Contracts\ServiceProviderInterface;
use Maduser\Argon\Container\ArgonContainer;

class SampleProvider implements ServiceProviderInterface
{
    public function register(ArgonContainer $container): void
    {
    }

    public function boot(ArgonContainer $container): void
    {
    }
}
