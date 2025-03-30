<?php

namespace Tests\Unit\Container\Mocks;

class Foo
{
    public string $message;

    public function __construct(string $message)
    {
        $this->message = $message;
    }
}