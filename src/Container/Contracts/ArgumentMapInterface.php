<?php

declare(strict_types=1);

namespace Maduser\Argon\Container\Contracts;

/**
 * Stores arguments scoped by identifier (typically FQCN).
 */
interface ArgumentMapInterface
{
    /**
     * @param array<string, array<string, mixed>> $arguments
     */
    public function setArguments(array $arguments): void;

    public function has(string $serviceId, string $argument): bool;

    /**
     * @param string $serviceId
     * @param array $arguments
     *
     * @return void
     */
    public function set(string $serviceId, array $arguments): void;


    /**
     * @param string     $serviceId
     * @param string $key
     * @param mixed|null $default
     *
     * @return mixed
     */
    public function getArgument(string $serviceId, string $key, mixed $default = null): mixed;

    /**
     * @param string     $serviceId
     *
     * @return array<string, mixed>
     */
    public function get(string $serviceId): array;

    /**
     * @return array<string, array<string, mixed>>
     */
    public function all(): array;
}
