<?php

declare(strict_types=1);

namespace Maduser\Argon\Container\Interceptors\Contracts;

interface ValidationInterface
{
    public function validate(): void;
}
