<?php

namespace Tests\Unit\Container\Mocks;

class FooBarService
{
    public string $foo;
    public int $bar;

    public function __construct(string $foo, int $bar)
    {
        $this->foo = $foo;
        $this->bar = $bar;
    }
}