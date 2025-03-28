<?php

declare(strict_types=1);

namespace Tests\Integration\Mocks;

final class MixedParamWithDefault
{
    public function __construct(public mixed $data = 'foo')
    {
    }
}
