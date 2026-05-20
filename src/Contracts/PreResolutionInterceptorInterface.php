<?php

declare(strict_types=1);

namespace Maduser\Argon\Container\Contracts;

interface PreResolutionInterceptorInterface extends InterceptorInterface
{
    /**
     * @param array<array-key, mixed> $parameters
     */
    public function intercept(string $id, array &$parameters): ?object;
}
