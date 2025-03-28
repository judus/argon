<?php

declare(strict_types=1);

namespace Tests\Integration\Mocks;

class NeedsSomethingUnresolvable
{
    public function __construct(NonExistentDep $dep)
    {
    }
}
