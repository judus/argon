<?php

declare(strict_types=1);

namespace Maduser\Argon\Container;

class ParameterRegistry
{
    /**
     * Holds parameters scoped by identifier (usually FQCN).
     *
     * @var array<string, array<string, mixed>>
     */
    private array $parameters = [];

    public function __construct(array $parameters = [])
    {
        $this->parameters = $parameters;
    }

    /**
     * @param array $parameters
     * @return void
     */
    public function setParameters(array $parameters): void
    {
        $this->parameters = $parameters;
    }

    /**
     * Sets a scoped parameter set for a given key (e.g., FQCN).
     *
     * @param string $scope
     * @param array<string, mixed> $params
     */
    public function set(string $scope, array $params): void
    {
        $this->parameters[$scope] = $params;
    }

    /**
     * Retrieves all parameters for the given scope.
     *
     * @param string $scope
     * @return array<string, mixed>
     */
    public function get(string $scope): array
    {
        return $this->parameters[$scope] ?? [];
    }

    /**
     * Checks whether a parameter is defined under a scope.
     *
     * @param string $scope
     * @param string $name
     * @return bool
     */
    public function has(string $scope, string $name): bool
    {
        return isset($this->parameters[$scope][$name]);
    }

    /**
     * Gets a single parameter value under a specific scope.
     *
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
     * Returns all stored parameters.
     *
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        return $this->parameters;
    }
}
