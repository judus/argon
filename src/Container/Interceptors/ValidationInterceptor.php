<?php

declare(strict_types=1);

namespace Maduser\Argon\Container\Interceptors;

use Maduser\Argon\Container\Contracts\InterceptorInterface;
use Maduser\Argon\Container\Interceptors\Contracts\ValidationInterface;

/**
 * Intercepts resolved instances that implement ValidationInterface and triggers validation logic.
 */
final readonly class ValidationInterceptor implements InterceptorInterface
{
    public static function supports(object|string $target): bool
    {
        return $target instanceof ValidationInterface
            || (is_string($target) && is_subclass_of($target, ValidationInterface::class));
    }

    public function intercept(object $instance): void
    {
        if ($instance instanceof ValidationInterface) {
            $instance->validate();
        }
    }
}
