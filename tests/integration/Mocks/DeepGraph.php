<?php

declare(strict_types=1);

namespace Tests\Integration\Mocks;

final class DeepGraph
{
    public function __construct(public MidLevel $mid)
    {
    }
}
