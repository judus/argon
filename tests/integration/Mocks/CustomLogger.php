<?php

declare(strict_types=1);

namespace Tests\Integration\Mocks;

final class CustomLogger implements LoggerInterface
{
    public string $label = 'custom';

    public function log(string $message): string
    {
        return "[{$this->label}] $message";
    }
}
