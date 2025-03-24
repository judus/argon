<?php

declare(strict_types=1);

namespace Tests\Mocks;

class MyService
{
    public bool $called = false;

    public function doSomething(string $msg): void
    {
        $this->called = true;
    }
}
