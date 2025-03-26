<?php

declare(strict_types=1);

namespace Maduser\Argon\Container\Contracts;

/**
 * Registry for pre- and post-resolution interceptors.
 */
interface InterceptorRegistryInterface
{
    /**
     * Registers a post-resolution interceptor.
     *
     * @param class-string<PostResolutionInterceptorInterface> $interceptor
     */
    public function registerPost(string $interceptor): void;

    /**
     * Registers a pre-resolution interceptor.
     *
     * @param class-string<PreResolutionInterceptorInterface> $interceptor
     */
    public function registerPre(string $interceptor): void;

    /**
     * @return array<class-string<PostResolutionInterceptorInterface>>
     */
    public function allPost(): array;

    /**
     * @return array<class-string<PreResolutionInterceptorInterface>>
     */
    public function allPre(): array;

    /**
     * Match and apply a post-resolution interceptor.
     */
    public function matchPost(object $instance): object;

    /**
     * Match and return a pre-resolution interceptor (or null).
     */
    public function matchPre(string $id, array $parameters = []): ?PreResolutionInterceptorInterface;
}
