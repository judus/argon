<?php

namespace Maduser\Argon\Container\Contracts;

interface PreResolutionInterceptorInterface extends InterceptorInterface
{

    public function intercept(string $id, array &$parameters): ?object;

}