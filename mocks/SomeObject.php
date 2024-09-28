<?php

namespace Maduser\Argon\Mocks;

class SomeObject
{
    public SingletonObject $singletonObject;

    /**
     * @param SingletonObject $singletonObject
     */
    public function __construct(SingletonObject $singletonObject)
    {
        $this->singletonObject = $singletonObject;
    }
}