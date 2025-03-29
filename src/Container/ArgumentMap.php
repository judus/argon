<?php

declare(strict_types=1);

namespace Maduser\Argon\Container;

use Maduser\Argon\Container\Contracts\ArgumentMapInterface;

final class ArgumentMap implements ArgumentMapInterface
{
    /** @var array<string, array<string, mixed>> */
    private array $map = [];

    public function __construct(array $map = [])
    {
        $this->map = $map;
    }

    public function setArguments(array $arguments): void
    {
        $this->map = $arguments;
    }

    public function get(string $serviceId): array
    {
        return $this->map[$serviceId] ?? [];
    }

    public function set(string $serviceId, array $arguments): void
    {
        $this->map[$serviceId] = $arguments;
    }

    public function getArgument(string $serviceId, string $key, mixed $default = null): mixed
    {
        return $this->map[$serviceId][$key] ?? $default;
    }

    public function has(string $serviceId, string $argument): bool
    {
        return isset($this->map[$serviceId][$argument]);
    }

    /** @return array<string, array<string, mixed>> */
    public function all(): array
    {
        return $this->map;
    }
}
