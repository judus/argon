<?php

declare(strict_types=1);

namespace Tests\Integration\Compiler\Mocks;

class WithOptionalService
{
    public ?Logger $logger = null;
    public function __construct(
        ?Logger $logger = null
    ) {
        $this->logger = $logger;
    }
}
