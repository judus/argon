<?php

declare(strict_types=1);

namespace Tests\Integration\Mocks;

final class NullableUnknown
{
    public function __construct(public ?UnknownLogger $logger = null)
    {
    }
}
