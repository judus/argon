<?php

declare(strict_types=1);

namespace Tests\Unit\Container\Compiler\Mocks;

final class DummyBindings
{
    public function __construct(private array $bindings = [])
    {
    }

    public function has(string $context, string $type): bool
    {
        return isset($this->bindings[$context][$type]);
    }

    public function get(string $context, string $type): string
    {
        return $this->bindings[$context][$type];
    }
}
