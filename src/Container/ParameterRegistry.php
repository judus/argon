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
     * @var array<string, array<string, mixed>>
     */
    private array $parameters = [];

    public function __construct(array $parameters = [])
    {
        $this->parameters = $parameters;
    }

    /**
     * @param array<string, array<string, mixed>> $parameters
     */
    public function setParameters(array $parameters): void
    {
        $this->parameters = $parameters;
    }

    /**
     * @param string $scope
     * @param array<string, mixed> $params
     */
    public function set(string $scope, array $params): void
    {
        $this->parameters[$scope] = $params;
    }

    /**
     * @param string $scope
     * @return array<string, mixed>
     */
    public function get(string $scope): array
    {
        return $this->parameters[$scope] ?? [];
    }

    /**
     * @param string $scope
     * @param string $name
     */
    public function has(string $scope, string $name): bool
    {
        return isset($this->parameters[$scope][$name]);
    }

    /**
     * @param string $scope
     * @param string $name
     * @param mixed|null $default
     * @return mixed
     */
    public function getScoped(string $scope, string $name, mixed $default = null): mixed
    {
        return $this->parameters[$scope][$name] ?? $default;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        return $this->parameters;
    }
}
