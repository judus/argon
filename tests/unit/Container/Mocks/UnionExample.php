<?php

declare(strict_types=1);

namespace Tests\Unit\Container\Mocks;

class UnionExample
{
    public function __construct(public Logger|Mailer|SomethingElse $dependency)
    {
    }
}
