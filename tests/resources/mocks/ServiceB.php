<?php

declare(strict_types=1);

namespace Tests\Mocks;

final class ServiceB
{
    public function __construct(public LoggerInterface $logger)
    {
    }
}
