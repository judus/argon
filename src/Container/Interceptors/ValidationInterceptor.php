<?php

declare(strict_types=1);

namespace Maduser\Argon\Container\Interceptors;

use Maduser\Argon\Container\Contracts\TypeInterceptorInterface;
use Maduser\Argon\Container\Interceptors\Contracts\ValidationInterface;

class ValidationInterceptor implements TypeInterceptorInterface
{
    public static function supports(object|string $target): bool
    {
        return $target instanceof ValidationInterface
            || (is_string($target) && is_subclass_of($target, ValidationInterface::class));
    }

    public function intercept(object $instance): void
    {
        $instance->validate();
    }
}
