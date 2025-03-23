<?php

namespace Tests\Integration\Compiler\Mocks;

use Maduser\Argon\Container\Contracts\TypeInterceptorInterface;

class LoggerInterceptor implements TypeInterceptorInterface
{
    public static function supports(object|string $target): bool
    {
        return $target === Logger::class || $target instanceof Logger;
    }

    public function intercept(object $instance): void
    {
        if ($instance instanceof Logger) {
            $instance->intercepted = true;
        }
    }
}
