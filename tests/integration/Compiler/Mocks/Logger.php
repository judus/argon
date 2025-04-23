<?php

declare(strict_types=1);

namespace Tests\Integration\Compiler\Mocks;

use Tests\Integration\Mocks\LoggerInterface;

class Logger implements LoggerInterface
{
    public bool $intercepted = false;

    public function log(string $msg): string
    {
        return $msg;
    }
}
