<?php

declare(strict_types=1);

namespace Maduser\Argon\Container\Contracts;

use Maduser\Argon\Container\Exceptions\ContainerException;

/**
 * Manages registration and execution of type interceptors.
 */
interface InterceptorRegistryInterface
{
    /**
     * @return list<class-string<InterceptorInterface>>
     */
    public function all(): array;

    /**
     * @param class-string<InterceptorInterface> $interceptorClass
     * @throws ContainerException
     */
    public function register(string $interceptorClass): void;

    /**
     * Applies all supported interceptors to the given instance.
     *
     * @param object $instance
     * @return object
     */
    public function apply(object $instance): object;
}
