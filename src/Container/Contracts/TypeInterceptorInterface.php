<?php

declare(strict_types=1);

namespace Maduser\Argon\Container\Contracts;

interface TypeInterceptorInterface
{
    /**
     * Determines if the interceptor applies to the given service.
     *
     * @return bool True if the interceptor supports the service, false otherwise
     */
    public static function supports(object|string $target): bool;

    /**
     * Applies the interceptor's logic to the service instance.
     *
     * @param object $instance The resolved service instance
     *
     * @return void
     */
    public function intercept(object $instance): void;
}
