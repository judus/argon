<?php

declare(strict_types=1);

namespace Maduser\Argon\Container\Support;

use Maduser\Argon\Container\Contracts\CallableWrapperInterface;
use ReflectionFunctionAbstract;

final readonly class CallableWrapper implements CallableWrapperInterface
{
    public function __construct(
        public ?object $instance,
        public ReflectionFunctionAbstract $reflection
    ) {
    }
}
