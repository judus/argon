<?php

declare(strict_types=1);

namespace Tests\Unit\Container\Compiler\Stubs;

use stdClass;

final class PrivateFactory
{
    /** @psalm-suppress UnusedMethod */
    private function create(): stdClass
    {
        return new stdClass();
    }
}
