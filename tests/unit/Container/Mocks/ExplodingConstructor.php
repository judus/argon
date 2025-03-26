<?php

declare(strict_types=1);

namespace Tests\Unit\Container\Mocks;

class ExplodingConstructor
{
    public function __construct()
    {
        throw new \RuntimeException("Constructor boom");
    }
}
