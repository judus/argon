<?php

declare(strict_types=1);

namespace Tests\Integration\Mocks;

final class NeedsUnknown
{
    public function __construct(UnknownThing $x)
    {
    }
}
