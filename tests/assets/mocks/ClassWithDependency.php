<?php

namespace Tests\Mocks;

class ClassWithDependency
{
    public DependencyClass $dependency;

    public function __construct(DependencyClass $dependency)
    {
        $this->dependency = $dependency;
    }
}
