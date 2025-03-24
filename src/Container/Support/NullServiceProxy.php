<?php

declare(strict_types=1);

namespace Maduser\Argon\Container\Support;

final class NullServiceProxy
{
    public function __call(string $name, array $arguments): void
    {
        // Silently do nothing
    }

    public function __get(string $name): null
    {
        return null;
    }

    public function __set(string $name, $value): void
    {
        // Ignore
    }

    public function __isset(string $name): bool
    {
        return false;
    }

    public function __unset(string $name): void
    {
        // Ignore
    }
}