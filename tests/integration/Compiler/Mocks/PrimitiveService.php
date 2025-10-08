<?php

declare(strict_types=1);

namespace Tests\Integration\Compiler\Mocks;

final class PrimitiveService
{
    public function __construct(public string $path)
    {
    }
}
