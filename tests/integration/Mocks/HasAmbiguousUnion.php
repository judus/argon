<?php

declare(strict_types=1);

namespace Tests\Integration\Mocks;

final class HasAmbiguousUnion
{
    public function __construct(public Logger|Bar $thing)
    {
    }
}
