<?php

declare(strict_types=1);

namespace Maduser\Argon\Container\Contracts;

interface TypeInterceptorInterface
{
    /**
     * Determines if the interceptor applies to the given service.
     *
     * @param object $service The resolved service instance
     *
     * @return bool True if the interceptor supports the service, false otherwise
     */
    public function supports(object $service): bool;

    /**
     * Applies the interceptor's logic to the service instance.
     *
     * @param object $service The resolved service instance
     *
     * @return void
     */
    public function intercept(object $service): void;
}
