<?php

declare(strict_types=1);

namespace Tests\Integration\Compiler\Mocks;

use Tests\Integration\Mocks\LoggerInterface;

final class WithOptionalInterface
{
    public ?LoggerInterface $logger = null;
    public function __construct(
        ?LoggerInterface $logger = null
    ) {
        $this->logger = $logger;
    }
}
