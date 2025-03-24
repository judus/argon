<?php

declare(strict_types=1);

namespace Tests\Integration\Interceptors\Mocks;

class NeedsInit
{
    public bool $initialized = false;

    public function init(): void
    {
        $this->initialized = true;
    }
}
