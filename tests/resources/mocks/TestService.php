<?php

declare(strict_types=1);

namespace Tests\Mocks;

class TestService
{
    public bool $called = false;

    private string $dependency;

    // Constructor to initialize dependency
    public function __construct(string $dependency)
    {
        $this->dependency = $dependency;
    }

    // Method that accepts a parameter (which we will override in the test)
    public function someMethod(string $dependency = 'defaultValue'): string
    {
        return $dependency;
    }

    public function getDependency(): string
    {
        return $this->dependency;
    }

    public function callMe(): void
    {
        $this->called = true;
    }
}
