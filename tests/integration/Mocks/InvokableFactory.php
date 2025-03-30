<?php

namespace Tests\Integration\Mocks;

class InvokableFactory
{
    public function __invoke(): Foo
    {
        return new Foo('from-invokable');
    }
}