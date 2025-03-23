<?php

namespace Tests\Integration\Compiler\Mocks;

class Mailer
{
    public Logger $logger;

    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
    }
}
