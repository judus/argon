<?php

declare(strict_types=1);

namespace Maduser\Argon\Container\Contracts;

/**
 * Contract for service interceptors that apply conditional logic to resolved instances.
 *
 * Interceptors may inspect the target (class name or instance) to decide if they apply.
 */
interface InterceptorInterface
{
    /**
     * Determines if the interceptor should apply to the given service.
     *
     * @param string|object $target
     * @return bool
     */
    public static function supports(object|string $target): bool;
}
