<?php

declare(strict_types=1);

namespace Tests\Integration\Compiler\Mocks;

class DefaultValueService
{
    public function __construct(string $foo = 'default-val')
    {
    }
}
