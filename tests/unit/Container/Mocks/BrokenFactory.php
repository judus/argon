<?php

declare(strict_types=1);

namespace Tests\Unit\Container\Mocks;

final class BrokenFactory
{
    public function __construct()
    {
    }
    // Purposely no method.
}
