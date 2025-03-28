<?php

declare(strict_types=1);

namespace Tests\Integration\Mocks;

final class HasMixedParam
{
    public function __construct(public mixed $data)
    {
    }
}
