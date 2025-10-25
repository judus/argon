<?php

declare(strict_types=1);

namespace Tests\Integration\Mocks;

use Maduser\Argon\Container\Contracts\PostResolutionInterceptorInterface;
use Tests\Integration\Mocks\InterceptedClass;

final class Post2 implements PostResolutionInterceptorInterface
{
    #[\Override]
    public static function supports(object|string $target): bool
    {
        return $target instanceof InterceptedClass;
    }

    #[\Override]
    public function intercept(object $instance): void
    {
        $instance->post2 = true;
    }
}
