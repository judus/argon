<?php

declare(strict_types=1);

namespace Tests\Integration\Mocks;

final class NeedsNullable
{
    public function __construct(public ?Logger $logger = null)
    {
    }
}
