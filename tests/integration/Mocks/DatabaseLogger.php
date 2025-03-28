<?php

declare(strict_types=1);

namespace Tests\Integration\Mocks;

class DatabaseLogger implements LoggerInterface
{
    public function log(string $message): void
    {
    }
}
