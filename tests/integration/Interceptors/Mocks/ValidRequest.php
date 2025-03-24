<?php

declare(strict_types=1);

namespace Tests\Integration\Interceptors\Mocks;

use Maduser\Argon\Container\Interceptors\Contracts\ValidationInterface;

class ValidRequest implements ValidationInterface
{
    public bool $wasValidated = false;

    public function validate(): void
    {
        $this->wasValidated = true;
    }
}
