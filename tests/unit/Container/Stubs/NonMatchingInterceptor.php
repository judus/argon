<?php

namespace Tests\Unit\Container\Stubs;

use LogicException;
use Maduser\Argon\Container\Contracts\InterceptorInterface;

/**
 * A stub interceptor that never supports anything.
 */
class NonMatchingInterceptor implements InterceptorInterface
{
    public static function supports(object|string $target): bool
    {
        return false;
    }

    public function intercept(object $instance): void
    {
        throw new LogicException('Should not be called');
    }
}