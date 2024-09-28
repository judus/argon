<?php

declare(strict_types=1);

namespace Tests\Mocks;

/**
 * @psalm-immutable
 */
class SomeObjectDependsOnSingleton
{
    public SomeObject $singletonObject;

    public function __construct(SomeObject $singletonObject)
    {
        $this->singletonObject = $singletonObject;
    }
}
