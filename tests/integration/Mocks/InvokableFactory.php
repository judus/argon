<?php

declare(strict_types=1);

namespace Tests\Integration\Mocks;

final class InvokableFactory
{
    public function __invoke(): Foo
    {
        return new Foo('from-invokable');
    }
}
