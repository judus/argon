<?php

declare(strict_types=1);

namespace Tests\Integration\Interceptors\Mocks;

use Maduser\Argon\Container\Interceptors\Contracts\InitInterface;

class NeedsInit implements InitInterface
{
    public bool $initialized = false;

    public function init(): void
    {
        $this->initialized = true;
    }
}
