<?php

declare(strict_types=1);

namespace Tests\Integration\Mocks;

final class NeedsSomethingUnresolvable
{
    public function __construct(NonExistentDep $dep)
    {
    }
}
