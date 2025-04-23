<?php

declare(strict_types=1);

namespace Tests\Unit\Container\Compiler\Mocks;

final class DummyContainer
{
    public array $hasServices = [];
    public array $descriptorArgs = [];

    public function has(string $id): bool
    {
        return in_array($id, $this->hasServices, true);
    }

    public function getDescriptor(string $serviceId): ?object
    {
        return new class ($this->descriptorArgs) {
            public function __construct(private array $args)
            {
            }

            public function hasArgument(string $key): bool
            {
                return array_key_exists($key, $this->args);
            }

            public function getArgument(string $key): mixed
            {
                return $this->args[$key] ?? null;
            }
        };
    }

    public function get(string $id): object
    {
        return new \stdClass();
    }
}
