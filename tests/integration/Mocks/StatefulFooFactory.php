<?php

declare(strict_types=1);

namespace Tests\Integration\Mocks;

final class StatefulFooFactory
{
    public int $calls = 0;

    public function __construct(public readonly string $label)
    {
    }

    public function make(string $label): Foo
    {
        $this->calls++;

        return new Foo($this->label . ':' . $label);
    }
}
