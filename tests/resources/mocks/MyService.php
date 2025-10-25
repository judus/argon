<?php

declare(strict_types=1);

namespace Tests\Mocks;

final class MyService
{
    public bool $called = false;

    public function doSomething(string $_msg): void
    {
        $this->called = true;
    }
}
