<?php

declare(strict_types=1);

namespace Tests\Integration\Mocks;

class FooFactory
{
    public function make(): Foo
    {
        return new Foo('made-by-factory');
    }

    public function makeWithArgs(string $label): Foo
    {
        return new Foo($label);
    }

    public function makeWithDefault(string $label = 'default-label'): Foo
    {
        return new Foo($label);
    }}
