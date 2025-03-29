<?php

declare(strict_types=1);

namespace Tests\Integration\Mocks;

class NeedsLogger
{
    public function __construct(
        public LoggerInterface $logger
    ) {
    }
}
