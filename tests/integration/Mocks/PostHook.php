<?php

declare(strict_types=1);

namespace Tests\Integration\Mocks;

use Maduser\Argon\Container\Contracts\PostResolutionInterceptorInterface;
use Tests\Integration\Mocks\Validatable;

final class PostHook implements PostResolutionInterceptorInterface
{
    public static function supports(object|string $target): bool
    {
        return is_subclass_of($target, Validatable::class);
    }

    public function intercept(object $instance): void
    {
        $instance->validate();
    }
}
