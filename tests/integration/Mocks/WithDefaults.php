<?php

declare(strict_types=1);

namespace Tests\Integration\Mocks;

final class WithDefaults
{
    public function __construct(public string $value = 'default')
    {
    }
}
