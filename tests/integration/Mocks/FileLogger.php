<?php

declare(strict_types=1);

namespace Tests\Integration\Mocks;

final class FileLogger implements LoggerInterface
{
    public function log(string $message): void
    {
    }
}
