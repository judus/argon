<?php

declare(strict_types=1);

namespace Tests\Unit\Container\Mocks;

use Maduser\Argon\Container\Interceptors\Contracts\InitInterface;

class MockInit implements InitInterface
{
    public function init(): void
    {
    }
}
