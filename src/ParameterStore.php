<?php

declare(strict_types=1);

namespace Maduser\Argon\Container;

use Maduser\Argon\Container\Contracts\ParameterStoreInterface;

final class ParameterStore implements ParameterStoreInterface
{
    private array $store = [];

    #[\Override]
    public function setStore(array $store): void
    {
        $this->store = $store;
    }

    #[\Override]
    public function set(string $key, int|string|bool|null $value): void
    {
        $this->store[$key] = $value;
    }

    #[\Override]
    public function get(string $key, int|string|bool|null $default = null): mixed
    {
        return $this->store[$key] ?? $default;
    }

    #[\Override]
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->store);
    }

    #[\Override]
    public function all(): array
    {
        return $this->store;
    }
}
