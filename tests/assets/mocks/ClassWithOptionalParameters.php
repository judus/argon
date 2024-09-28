<?php

namespace Tests\Mocks;

class ClassWithOptionalParameters
{
    public string $param;

    public function __construct(string $param = 'default')
    {
        $this->param = $param;
    }
}
