<?php

declare(strict_types=1);

namespace Tests\Integration\Mocks;

use Maduser\Argon\Container\Contracts\PostResolutionInterceptorInterface;
use Tests\Integration\Mocks\InterceptedClass;

final class SingletonPostInterceptor implements PostResolutionInterceptorInterface
{
    public static int $called = 0;

    #[\Override]
    public static function supports(string|object $target): bool
    {
        return $target === InterceptedClass::class;
    }

    #[\Override]
    public function intercept(object $instance): void
    {
        self::$called++;
    }

    public static function reset(): void
    {
        self::$called = 0;
    }
}
