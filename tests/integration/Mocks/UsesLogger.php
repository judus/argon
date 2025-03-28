<?php

declare(strict_types=1);

namespace Tests\Integration\Mocks;

final class UsesLogger
{
    public function __construct(public Logger $logger)
    {
    }

    public function reportSomething(): string
    {
        return 'Reported by logger';
    }
}
