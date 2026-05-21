<?php

declare(strict_types=1);

namespace Tests\Integration\Mocks;

final class InvalidFooFactory
{
    public function makeString(): string
    {
        return 'not-a-service';
    }
}
