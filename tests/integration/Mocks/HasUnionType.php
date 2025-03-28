<?php

declare(strict_types=1);

namespace Tests\Integration\Mocks;

final class HasUnionType
{
    public function __construct(public Logger|string $loggerOrString)
    {
    }
}
