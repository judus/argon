<?php

namespace Tests\Mocks;

use Maduser\Argon\Container\ServiceContainer;

class ClassWithContainerDependency
{
    public ServiceContainer $container;

    public function __construct(ServiceContainer $container)
    {
        $this->container = $container;
    }
}
