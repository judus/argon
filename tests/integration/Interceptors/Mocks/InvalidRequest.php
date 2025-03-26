<?php

declare(strict_types=1);

namespace Tests\Integration\Interceptors\Mocks;

use InvalidArgumentException;
use Maduser\Argon\Container\Interceptors\Post\Contracts\ValidationInterface;

class InvalidRequest implements ValidationInterface
{
    public function validate(): void
    {
        throw new InvalidArgumentException('Title is required.');
    }
}
