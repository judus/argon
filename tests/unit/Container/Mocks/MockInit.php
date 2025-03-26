<?php

declare(strict_types=1);

namespace Tests\Unit\Container\Mocks;

use Maduser\Argon\Container\Interceptors\Post\Contracts\InitInterface;

class MockInit implements InitInterface
{
    public function init(): void
    {
    }
}
