<?php

declare(strict_types=1);

namespace Tests\Integration\Compiler\Mocks;

final class StatefulDefaultValueFactory
{
    public function __construct(private readonly string $label)
    {
    }

    public function create(string $label): DefaultValueService
    {
        return new DefaultValueService($this->label . ':' . $label);
    }
}
