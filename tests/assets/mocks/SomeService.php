<?php

namespace Tests\Mocks;

use Psr\Log\LoggerInterface;

class SomeService
{
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function doSomething($message): void
    {
        $this->logger->info($message);
    }
}