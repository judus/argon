<?php

declare(strict_types=1);

namespace Tests\Unit\Container\Compiler\Mocks;

final readonly class DummyBindings
{
    public function __construct(
        /**
         * @var array<array-key, array<array-key, string>>
         */
        private array $bindings = []
    ) {
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
