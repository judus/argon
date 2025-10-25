<?php

declare(strict_types=1);

namespace Tests\Mocks;

final class TestServiceWithNonExistentDependency
{
    public function __construct(NonExistentDependency $_dependency)
    {
    }
}
