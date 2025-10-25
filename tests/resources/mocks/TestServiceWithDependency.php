<?php

declare(strict_types=1);

namespace Tests\Mocks;

final class TestServiceWithDependency
{
    private TestDependency $dependency;

    public function __construct(TestDependency $dependency)
    {
        $this->dependency = $dependency;
    }

    public function getDependency(): TestDependency
    {
        return $this->dependency;
    }
}
