<?php

declare(strict_types=1);

namespace Tests\Integration\Mocks;

class ServiceB
{
    public function __construct(public LoggerInterface $logger)
    {
    }
}
