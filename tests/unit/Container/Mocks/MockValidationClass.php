<?php

declare(strict_types=1);

namespace Tests\Unit\Container\Mocks;

use Maduser\Argon\Container\Interceptors\Contracts\ValidationInterface;

class MockValidationClass implements ValidationInterface
{
    public function validate(): void
    {
    }
}
