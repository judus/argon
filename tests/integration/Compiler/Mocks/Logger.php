<?php

declare(strict_types=1);

namespace Tests\Integration\Compiler\Mocks;

class Logger
{
    public bool $intercepted = false;

    public function log(string $msg): string
    {
        return $msg;
    }
}
