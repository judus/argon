<?php

declare(strict_types=1);

namespace Tests\Integration\Mocks;

final class SimpleService
{
    public function __construct(public string $value)
    {
    }
}
