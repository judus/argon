<?php

declare(strict_types=1);

namespace Tests\Integration\Mocks;

final class NeedsScalar
{
    public function __construct(public string $val)
    {
    }
}
