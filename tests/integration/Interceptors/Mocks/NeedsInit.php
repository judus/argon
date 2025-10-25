<?php

declare(strict_types=1);

namespace Tests\Integration\Interceptors\Mocks;

use Maduser\Argon\Container\Interceptors\Post\Contracts\InitInterface;

final class NeedsInit implements InitInterface
{
    public bool $initialized = false;

    #[\Override]
    public function init(): void
    {
        $this->initialized = true;
    }
}
