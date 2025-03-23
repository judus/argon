<?php

declare(strict_types=1);

namespace Tests\Mocks;

class ServiceB
{
    public function __construct(public LoggerInterface $logger)
    {
    }
}
