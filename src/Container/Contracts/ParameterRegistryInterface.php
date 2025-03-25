<?php

declare(strict_types=1);

namespace Maduser\Argon\Container\Contracts;

/**
 * @internal
 * Registry for named parameter sets scoped by identifier (e.g., class names).
 */
interface ParameterRegistryInterface
{
    /**
     * @param string $scope
     * @param array<string, mixed> $params
     * @return void
     */
    public function set(string $scope, array $params): void;

    /**
     * @param string $scope
     * @return array<string, mixed>
     */
    public function get(string $scope): array;

    /**
     * @param string $scope
     * @param string $name
     * @param mixed|null $default
     * @return mixed
     */
    public function getScoped(string $scope, string $name, mixed $default = null): mixed;

    /**
     * @param string $scope
     * @param string $name
     * @return bool
     */
    public function has(string $scope, string $name): bool;

    /**
     * @return array<string, array<string, mixed>>
     */
    public function all(): array;
}
