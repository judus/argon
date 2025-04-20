<?php

declare(strict_types=1);

namespace Maduser\Argon\Container;

use Maduser\Argon\Container\Contracts\ParameterStoreInterface;

final class ParameterStore implements ParameterStoreInterface
{
    private array $store = [];

    public function setStore(array $store): void
    {
        $this->store = $store;
    }

    public function set(string $key, string|int|bool|null $value): void
    {
        $this->store[$key] = $value;
    }

    /**
     * @param string $key
     * @param string|int|bool|null $default
     *
     * @return mixed
     */
    public function get(string $key, string|int|bool|null $default = null): mixed
    {
        return $this->store[$key] ?? $default;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->store);
    }

    public function all(): array
    {
        return $this->store;
    }
}
