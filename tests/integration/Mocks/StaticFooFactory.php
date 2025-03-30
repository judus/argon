<?php

namespace Tests\Integration\Mocks;

class StaticFooFactory
{
    public static function createStatic(): Foo
    {
        return new Foo('from-static-method');
    }
}