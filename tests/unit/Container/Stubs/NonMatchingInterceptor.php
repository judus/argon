<?php

declare(strict_types=1);

namespace Tests\Unit\Container\Stubs;

use LogicException;
use Maduser\Argon\Container\Contracts\InterceptorInterface;
use Maduser\Argon\Container\Contracts\PostResolutionInterceptorInterface;

/**
 * A stub interceptor that never supports anything.
 */
final class NonMatchingInterceptor implements PostResolutionInterceptorInterface
{
    #[\Override]
    public static function supports(object|string $target): bool
    {
        return false;
    }

    #[\Override]
    public function intercept(object $instance): void
    {
        throw new LogicException('Should not be called');
    }
}
