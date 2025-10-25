<?php

declare(strict_types=1);

namespace Tests\Integration\Mocks;

final class DatabaseLogger implements LoggerInterface
{
    public function log(string $message): void
    {
    }
}
