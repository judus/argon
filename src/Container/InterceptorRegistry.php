<?php

declare(strict_types=1);

namespace Maduser\Argon\Container;

use Maduser\Argon\Container\Contracts\InterceptorRegistryInterface;
use Maduser\Argon\Container\Contracts\PostResolutionInterceptorInterface;
use Maduser\Argon\Container\Contracts\PreResolutionInterceptorInterface;
use Maduser\Argon\Container\Exceptions\ContainerException;

/**
 * Manages registration and execution of type interceptors.
 */
final class InterceptorRegistry implements InterceptorRegistryInterface
{
    /** @var array<class-string<PostResolutionInterceptorInterface>> */
    private array $post = [];

    /** @var array<class-string<PreResolutionInterceptorInterface>> */
    private array $pre = [];

    /**
     * @var PostResolutionInterceptorInterface[]
     */
    private array $postInstances = [];

    /**
     * @var PreResolutionInterceptorInterface[]
     */
    private array $preInstances = [];


    /**
     * @throws ContainerException
     */
    public function registerPost(string $interceptor): void
    {
        if (!class_exists($interceptor)) {
            throw ContainerException::fromInterceptor(
                $interceptor,
                "Interceptor class '$interceptor' does not exist."
            );
        }

        if (!is_subclass_of($interceptor, PostResolutionInterceptorInterface::class)) {
            throw ContainerException::fromInterceptor(
                $interceptor,
                "Interceptor '$interceptor' must implement PostResolutionInterceptorInterface."
            );
        }

        $this->post[] = $interceptor;
    }

    /**
     * @throws ContainerException
     */
    public function registerPre(string $interceptor): void
    {
        if (!class_exists($interceptor)) {
            throw ContainerException::fromInterceptor(
                $interceptor,
                "Interceptor class '$interceptor' does not exist."
            );
        }

        if (!is_subclass_of($interceptor, PreResolutionInterceptorInterface::class)) {
            throw ContainerException::fromInterceptor(
                $interceptor,
                "Interceptor '$interceptor' must implement PreResolutionInterceptorInterface."
            );
        }

        $this->pre[] = $interceptor;
    }

    /**
     * @return array<class-string<PostResolutionInterceptorInterface>>
     */
    public function allPost(): array
    {
        return $this->post;
    }

    /**
     * @return array<class-string<PreResolutionInterceptorInterface>>
     */
    public function allPre(): array
    {
        return $this->pre;
    }

    /**
     * Applies all supported interceptors to the given instance.
     *
     * @param object $instance
     * @return object
     */
    public function matchPost(object $instance): object
    {
        foreach ($this->post as $interceptorClass) {
            if (!isset($this->postInstances[$interceptorClass])) {
                $this->postInstances[$interceptorClass] = new $interceptorClass();
            }

            $interceptor = $this->postInstances[$interceptorClass];

            if ($interceptorClass::supports($instance)) {
                $interceptor->intercept($instance);
            }
        }

        return $instance;
    }

    /**
     * @param string $id
     * @param array &$parameters
     *
     * @return object|null
     */
    public function matchPre(string $id, array &$parameters = []): ?object
    {
        foreach ($this->pre as $interceptorClass) {
            if (!isset($this->preInstances[$interceptorClass])) {
                $this->preInstances[$interceptorClass] = new $interceptorClass();
            }

            if ($interceptorClass::supports($id)) {
                $instance = $this->preInstances[$interceptorClass]->intercept($id, $parameters);

                if ($instance !== null) {
                    return $instance;
                }
            }
        }

        return null;
    }
}
