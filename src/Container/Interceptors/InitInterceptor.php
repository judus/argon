<?php

declare(strict_types=1);

namespace Maduser\Argon\Container\Interceptors;

use Maduser\Argon\Container\Contracts\InterceptorInterface;
use Maduser\Argon\Container\Interceptors\Contracts\InitInterface;

/**
 * Intercepts resolved instances implementing InitInterface and triggers their init() method.
 */
final readonly class InitInterceptor implements InterceptorInterface
{
    public static function supports(object|string $target): bool
    {
        return $target instanceof InitInterface
            || (is_string($target) && is_subclass_of($target, InitInterface::class));
    }

    public function intercept(object $instance): void
    {
        if ($instance instanceof InitInterface) {
            $instance->init();
        }
    }
}
