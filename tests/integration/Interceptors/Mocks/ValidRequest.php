<?php

declare(strict_types=1);

namespace Tests\Integration\Interceptors\Mocks;

use Maduser\Argon\Container\Interceptors\Post\Contracts\ValidationInterface;

class ValidRequest implements ValidationInterface
{
    public bool $wasValidated = false;

    public function __construct(protected Request $request)
    {
    }

    public function validate(): void
    {
        $this->wasValidated = true;
    }
}
