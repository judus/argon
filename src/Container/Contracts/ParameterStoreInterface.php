<?php

declare(strict_types=1);

namespace Maduser\Argon\Container\Contracts;

interface ParameterStoreInterface
{
    public function set(string $key, mixed $value): void;

    public function get(string $key, mixed $default = null): mixed;

    public function has(string $key): bool;

    /** @return array<string, mixed> */
    public function all(): array;
}
