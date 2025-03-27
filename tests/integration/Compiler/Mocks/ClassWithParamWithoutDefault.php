<?php

declare(strict_types=1);

namespace Tests\Integration\Compiler\Mocks;

class ClassWithParamWithoutDefault
{
    public mixed $value;

    public function __construct(mixed $value)
    {
        $this->value = $value;
    }
}
