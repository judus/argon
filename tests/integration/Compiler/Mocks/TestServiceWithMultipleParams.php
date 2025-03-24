<?php

declare(strict_types=1);

namespace Tests\Integration\Compiler\Mocks;

class TestServiceWithMultipleParams
{
    public function __construct(
        private readonly string $param1,
        private readonly int $param2
    ) {
    }

    public function getParam1(): string
    {
        return $this->param1;
    }

    public function getParam2(): int
    {
        return $this->param2;
    }
}
