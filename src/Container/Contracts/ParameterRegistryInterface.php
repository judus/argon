<?php

declare(strict_types=1);

namespace Maduser\Argon\Container\Contracts;

/**
 * Stores parameter sets scoped by identifier (typically FQCN).
 */
interface ParameterRegistryInterface
{
    /**
     * @param array<string, array<string, mixed>> $parameters
     */
    public function setParameters(array $parameters): void;

    /**
     * @param string               $key
     * @param mixed $value
     */
    public function set(string $key, mixed $value): void;

    /**
     * @param string $key
     *
     * @return mixed
     */
    public function get(string $key): mixed;

    /**
     * @param string $key
     *
     * @return bool
     */
    public function has(string $key): bool;

    /**
     * @param string $scope
     * @param string $key
     *
     * @return bool
     */
    public function scopeHas(string $scope, string $key): bool;

    /**
     * @param string $scope
     * @param array $values
     *
     * @return void
     */
    public function setScope(string $scope, array $values): void;


    /**
     * @param string     $scope
     * @param string $key
     * @param mixed|null $default
     *
     * @return mixed
     */
    public function getScoped(string $scope, string $key, mixed $default = null): mixed;

    /**
     * @param string     $scope
     *
     * @return array<string, mixed>
     */
    public function getScope(string $scope): array;

    /**
     * @return array<string, array<string, mixed>>
     */
    public function all(): array;

    /**
     * @return array<string, array<string, mixed>>
     */
    public function allScopes(): array;
}
