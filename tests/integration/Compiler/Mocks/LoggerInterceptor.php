<?php

declare(strict_types=1);

namespace Tests\Integration\Compiler\Mocks;

use Maduser\Argon\Container\Contracts\PostResolutionInterceptorInterface;

final class LoggerInterceptor implements PostResolutionInterceptorInterface
{
    #[\Override]
    public static function supports(object|string $target): bool
    {
        return $target === Logger::class || $target instanceof Logger;
    }

    #[\Override]
    public function intercept(object $instance): void
    {
        if ($instance instanceof Logger) {
            $instance->intercepted = true;
        }
    }
}
