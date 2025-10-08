<?php

declare(strict_types=1);

namespace Tests\Integration\Mocks;

final class ContextualLoggerHandler
{
    public function handle(LoggerInterface $logger): string
    {
        /** @var \Tests\Integration\Compiler\Mocks\Logger $logger */
        return $logger->log('invoked');
    }
}
