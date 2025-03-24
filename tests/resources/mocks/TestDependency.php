<?php

declare(strict_types=1);

namespace Tests\Mocks;

class TestDependency
{
    public function getData(): string
    {
        return 'dependency data';
    }
}
