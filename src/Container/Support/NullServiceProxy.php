<?php

declare(strict_types=1);

namespace Maduser\Argon\Container\Support;

final class NullServiceProxy
{
    /** @psalm-suppress UnusedParam */
    public function __call(string $name, array $arguments): void
    {
        // Silently do nothing
    }

    /** @psalm-suppress UnusedParam */
    public function __get(string $name): null
    {
        return null;
    }

    /** @psalm-suppress UnusedParam */
    public function __set(string $name, mixed $value): void
    {
        // Ignore
    }

    /** @psalm-suppress UnusedParam */
    public function __isset(string $name): bool
    {
        return false;
    }

    /** @psalm-suppress UnusedParam */
    public function __unset(string $name): void
    {
        // Ignore
    }
}
