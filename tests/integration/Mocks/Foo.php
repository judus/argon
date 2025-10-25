<?php

declare(strict_types=1);

namespace Tests\Integration\Mocks;

final class Foo
{
    public string $label;

    public function __construct(string $label = 'default')
    {
        $this->label = $label;
    }
}
