<?php

declare(strict_types=1);

namespace Maduser\Argon\Container\Contracts;

use ReflectionFunctionAbstract;

interface CallableWrapperInterface
{
    public function getReflection(): ReflectionFunctionAbstract;

    public function getInstance(): ?object;
}
