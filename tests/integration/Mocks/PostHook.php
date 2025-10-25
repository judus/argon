<?php

declare(strict_types=1);

namespace Tests\Integration\Mocks;

use Maduser\Argon\Container\Contracts\PostResolutionInterceptorInterface;
use Tests\Integration\Mocks\Validatable;

final class PostHook implements PostResolutionInterceptorInterface
{
    #[\Override]
    public static function supports(object|string $target): bool
    {
        return is_subclass_of($target, Validatable::class);
    }

    #[\Override]
    public function intercept(object $instance): void
    {
        $instance->validate();
    }
}
