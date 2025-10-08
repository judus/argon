<?php

declare(strict_types=1);

namespace Tests\Integration\Compiler\Mocks;

final class StaticService
{
    public static function sayHello(string $name): string
    {
        return 'Hello ' . $name;
    }
}
