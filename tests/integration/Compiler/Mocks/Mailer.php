<?php

declare(strict_types=1);

namespace Tests\Integration\Compiler\Mocks;

final class Mailer
{
    public Logger $logger;

    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
    }
}
