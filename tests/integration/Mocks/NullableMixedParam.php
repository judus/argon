<?php

declare(strict_types=1);

namespace Tests\Integration\Mocks;

final class NullableMixedParam
{
    public function __construct(public mixed $data = null)
    {
    }
}
