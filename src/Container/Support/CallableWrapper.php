<?php

declare(strict_types=1);

namespace Maduser\Argon\Container\Support;

use Maduser\Argon\Container\Contracts\CallableWrapperInterface;
use ReflectionFunctionAbstract;

readonly class CallableWrapper implements CallableWrapperInterface
{
    public function __construct(
        private ?object $instance,
        private ReflectionFunctionAbstract $reflection
    ) {
    }

    public function getReflection(): ReflectionFunctionAbstract
    {
        return $this->reflection;
    }

    public function getInstance(): ?object
    {
        return $this->instance;
    }
}
