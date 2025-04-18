<?php

declare(strict_types=1);

namespace Maduser\Argon\Container\Contracts;

interface ParameterStoreInterface
{
    public function setStore(array $store): void;

    /**
     * @param string $key
     * @param int|string|bool|null $value
     */
    public function set(string $key, int|string|bool|null $value): void;

    public function get(string $key, string|int|bool|null $default = null): mixed;

    public function has(string $key): bool;

    public function all(): array;
}
