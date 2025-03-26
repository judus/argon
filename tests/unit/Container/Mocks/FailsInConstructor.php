<?php

declare(strict_types=1);

namespace Tests\Unit\Container\Mocks;

class FailsInConstructor
{
    public function __construct()
    {
        throw new \RuntimeException('Boom');
    }
}
