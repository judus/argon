<?php

namespace Tests\Mocks;

class ClassWithInterfaceDependency
{
    public ExampleInterface $interfaceDependency;

    public function __construct(ExampleInterface $interfaceDependency)
    {
        $this->interfaceDependency = $interfaceDependency;
    }
}
