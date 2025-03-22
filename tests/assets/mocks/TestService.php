<?php

namespace Tests\Mocks;

class TestService
{
    private string $dependency;

    // Constructor to initialize dependency
    public function __construct(string $dependency)
    {
        $this->dependency = $dependency;
    }

    // Method that accepts a parameter (which we will override in the test)
    public function someMethod(string $dependency = 'defaultValue')
    {
        return $dependency;
    }

    public function getDependency(): string
    {
        return $this->dependency;
    }
}
