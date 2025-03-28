<?php

declare(strict_types=1);

namespace Tests\Integration\Mocks;

final class MidLevel
{
    public function __construct(public Logger $logger)
    {
    }
}
