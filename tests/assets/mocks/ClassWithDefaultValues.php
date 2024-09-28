<?php

namespace Tests\Mocks;

class ClassWithDefaultValues
{
    public string $param1;
    public int $param2;

    public function __construct(string $param1, int $param2 = 42)
    {
        $this->param1 = $param1;
        $this->param2 = $param2;
    }
}
