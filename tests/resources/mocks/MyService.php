<?php

namespace Tests\Mocks;

class MyService
{
    public bool $called = false;

    public function doSomething(string $msg): void
    {
        $this->called = true;
    }
}
