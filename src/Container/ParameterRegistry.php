<?php

declare(strict_types=1);

namespace Maduser\Argon\Container;

use Maduser\Argon\Container\Contracts\ParameterRegistryInterface;

/**
 * Stores parameter sets scoped by identifier (typically FQCN).
 */
final class ParameterRegistry implements ParameterRegistryInterface
{
    /**
     * @var array<string, mixed>
     */
    private array $parameters;

    /**
     * @var array<string, array<string, mixed>>
     */
    private array $arguments;

    public function __construct(array $parameters = [], array $arguments = [])
    {
        $this->parameters = $parameters;
        $this->arguments = $arguments;
    }

    /**
     * @param array<string, array<string, mixed>> $parameters
     */
    public function setParameters(array $parameters): void
    {
        $this->parameters = $parameters;
    }

    /**
     * @param string $key
     * @param mixed $value
     */
    public function set(string $key, mixed $value): void
    {
        $this->parameters[$key] = $value;
    }

    /**
     * @param string $key
     *
     * @return mixed
     */
    public function get(string $key): mixed
    {
        return $this->parameters[$key] ?? null;
    }

    /**
     * @param string $key
     *
     * @return bool
     */
    public function has(string $key): bool
    {
        return isset($this->parameters[$key]);
    }

    /**
     * @param string $scope
     * @param string $key
     *
     * @return bool
     */
    public function scopeHas(string $scope, string $key): bool
    {
        return isset($this->arguments[$scope][$key]);
    }

    public function setScope(string $scope, array $values): void
    {
        $this->arguments[$scope] = $values;
    }

    /**
     * @param string $scope
     * @param string $key
     * @param mixed|null $default
     *
     * @return mixed
     */
    public function getScoped(string $scope, string $key, mixed $default = null): mixed
    {
        return $this->arguments[$scope][$key] ?? $default;
    }

    /**
     * @param string $scope
     *
     * @return array<string, mixed>
     */
    public function getScope(string $scope): array
    {
        return $this->arguments[$scope] ?? [];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        return $this->parameters;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function allScopes(): array
    {
        return $this->arguments;
    }
}
