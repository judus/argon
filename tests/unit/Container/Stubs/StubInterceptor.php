<?php

declare(strict_types=1);

namespace Tests\Unit\Container\Stubs;

use Maduser\Argon\Container\Contracts\InterceptorInterface;
use Maduser\Argon\Container\Contracts\PostResolutionInterceptorInterface;
use stdClass;

/**
 * A stub interceptor that supports stdClass and adds a property.
 */
final class StubInterceptor implements PostResolutionInterceptorInterface
{
    #[\Override]
    public static function supports(object|string $target): bool
    {
        return $target instanceof stdClass || $target === stdClass::class;
    }

    #[\Override]
    public function intercept(object $instance): void
    {
        $instance->intercepted = true;
    }
}
