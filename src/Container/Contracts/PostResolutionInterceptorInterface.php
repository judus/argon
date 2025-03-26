<?php

namespace Maduser\Argon\Container\Contracts;

interface PostResolutionInterceptorInterface extends InterceptorInterface
{
    public function intercept(object $instance): void;
}