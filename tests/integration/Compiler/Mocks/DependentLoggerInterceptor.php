<?php

declare(strict_types=1);

namespace Tests\Integration\Compiler\Mocks;

use Maduser\Argon\Container\Contracts\PostResolutionInterceptorInterface;
use Tests\Integration\Mocks\CustomLogger;

final class DependentLoggerInterceptor implements PostResolutionInterceptorInterface
{
    public function __construct(private CustomLogger $logger)
    {
    }

    public static function supports(object|string $target): bool
    {
        return $target === Logger::class || $target instanceof Logger;
    }

    public function intercept(object $instance): void
    {
        if ($instance instanceof Logger) {
            $instance->intercepted = true;
            $instance->note = $this->logger->log('interceptor');
        }
    }
}
