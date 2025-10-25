<?php

declare(strict_types=1);

namespace Maduser\Argon\Container;

use Maduser\Argon\Container\Contracts\ArgumentMapInterface;
use Override;

final class ArgumentMap implements ArgumentMapInterface
{
    /** @var array<array-key, array<array-key, mixed>> */
    private array $map;

    /**
     * @param array<array-key, array<string, mixed>> $map
     */
    public function __construct(array $map = [])
    {
        $this->map = $map;
    }

    #[Override]
    public function setArguments(array $arguments): void
    {
        $this->map = $arguments;
    }

    #[Override]
    public function get(string $serviceId): array
    {
        return $this->map[$serviceId] ?? [];
    }

    /**
     * @param string $serviceId
     * @param array<array-key, mixed> $arguments
     * @return void
     */
    #[Override]
    public function set(string $serviceId, array $arguments): void
    {
        $this->map[$serviceId] = $arguments;
    }

    #[Override]
    public function getArgument(string $serviceId, string $key, mixed $default = null): mixed
    {
        return $this->map[$serviceId][$key] ?? $default;
    }

    #[Override]
    public function has(string $serviceId, string $argument): bool
    {
        return isset($this->map[$serviceId][$argument]);
    }

    /** @return array<array-key, array<array-key, mixed>> */
    #[Override]
    public function all(): array
    {
        return $this->map;
    }
}
