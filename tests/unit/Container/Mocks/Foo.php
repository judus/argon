<?php

declare(strict_types=1);

namespace Tests\Unit\Container\Mocks;

class Foo
{
    public string $message;

    public function __construct(string $message)
    {
        $this->message = $message;
    }
}
