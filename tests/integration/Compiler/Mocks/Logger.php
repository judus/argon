<?php

namespace Tests\Integration\Compiler\Mocks;

class Logger
{
    public bool $intercepted = false;

    public function log(string $msg): void
    {
    }
}
