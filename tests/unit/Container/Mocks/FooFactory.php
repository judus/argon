<?php

declare(strict_types=1);

namespace Tests\Unit\Container\Mocks;

class FooFactory
{
    public static function make(): Foo
    {
        return new Foo('made with make()');
    }

    public function __invoke(): Foo
    {
        return new Foo('Hello from FooFactory');
    }
}
