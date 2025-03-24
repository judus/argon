<?php

declare(strict_types=1);

namespace Tests\Mocks;

class TestServiceWithNonExistentDependency
{
    public function __construct(NonExistentDependency $dependency)
    {
    }
}
