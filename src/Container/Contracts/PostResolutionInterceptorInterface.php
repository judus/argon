<?php

declare(strict_types=1);

namespace Maduser\Argon\Container\Contracts;

interface PostResolutionInterceptorInterface extends InterceptorInterface
{
    public function intercept(object $instance): void;
}
