<?php

declare(strict_types=1);

namespace Maduser\Argon\Container;

use Maduser\Argon\Container\Contracts\InterceptorRegistryInterface;
use Maduser\Argon\Container\Contracts\PostResolutionInterceptorInterface;
use Maduser\Argon\Container\Contracts\PreResolutionInterceptorInterface;
use Maduser\Argon\Container\Contracts\ServiceResolverInterface;
use Maduser\Argon\Container\Exceptions\ContainerException;

/**
 * Manages registration and execution of type interceptors.
 */
final class InterceptorRegistry implements InterceptorRegistryInterface
{
    /** @var list<class-string<PostResolutionInterceptorInterface>> */
    private array $post = [];

    /** @var list<class-string<PreResolutionInterceptorInterface>> */
    private array $pre = [];

    /** @var array<class-string<PostResolutionInterceptorInterface>, PostResolutionInterceptorInterface> */
    private array $postInstances = [];

    /** @var array<class-string<PreResolutionInterceptorInterface>, PreResolutionInterceptorInterface> */
    private array $preInstances = [];

    private ?ServiceResolverInterface $resolver = null;

    public function setResolver(ServiceResolverInterface $resolver): void
    {
        $this->resolver = $resolver;
    }

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
     * @return list<class-string<PostResolutionInterceptorInterface>>
     */
    public function allPost(): array
    {
        return $this->post;
    }

    /**
     * @return list<class-string<PreResolutionInterceptorInterface>>
     */
    public function allPre(): array
    {
        return $this->pre;
    }

    public function matchPost(object $instance): object
    {
        foreach ($this->post as $interceptorClass) {
            if (!$interceptorClass::supports($instance)) {
                continue;
            }

            $interceptor = $this->getPostInstance($interceptorClass);
            $interceptor->intercept($instance);
        }

        return $instance;
    }

    public function matchPre(string $id, array &$parameters = []): ?object
    {
        foreach ($this->pre as $interceptorClass) {
            if (!$interceptorClass::supports($id)) {
                continue;
            }

            $interceptor = $this->getPreInstance($interceptorClass);
            $instance = $interceptor->intercept($id, $parameters);

            if ($instance !== null) {
                return $instance;
            }
        }

        return null;
    }

    /**
     * @param class-string<PostResolutionInterceptorInterface> $interceptorClass
     * @throws ContainerException
     */
    private function getPostInstance(string $interceptorClass): PostResolutionInterceptorInterface
    {
        if (!isset($this->postInstances[$interceptorClass])) {
            $resolved = $this->resolvePostInterceptor($interceptorClass);

            $this->postInstances[$interceptorClass] = $resolved;
        }

        return $this->postInstances[$interceptorClass];
    }

    /**
     * @param class-string<PreResolutionInterceptorInterface> $interceptorClass
     * @throws ContainerException
     */
    private function getPreInstance(string $interceptorClass): PreResolutionInterceptorInterface
    {
        if (!isset($this->preInstances[$interceptorClass])) {
            $resolved = $this->resolvePreInterceptor($interceptorClass);

            $this->preInstances[$interceptorClass] = $resolved;
        }

        return $this->preInstances[$interceptorClass];
    }

    /**
     * @param class-string<PostResolutionInterceptorInterface> $interceptorClass
     * @return PostResolutionInterceptorInterface
     */
    private function resolvePostInterceptor(string $interceptorClass): PostResolutionInterceptorInterface
    {
        $instance = $this->resolver !== null
            ? $this->resolver->resolve($interceptorClass)
            : new $interceptorClass();

        if (!$instance instanceof PostResolutionInterceptorInterface) {
            throw ContainerException::fromInterceptor(
                $interceptorClass,
                "Resolved interceptor must implement PostResolutionInterceptorInterface."
            );
        }

        return $instance;
    }

    /**
     * @param class-string<PreResolutionInterceptorInterface> $interceptorClass
     * @return PreResolutionInterceptorInterface
     */
    private function resolvePreInterceptor(string $interceptorClass): PreResolutionInterceptorInterface
    {
        $instance = $this->resolver !== null
            ? $this->resolver->resolve($interceptorClass)
            : new $interceptorClass();

        if (!$instance instanceof PreResolutionInterceptorInterface) {
            throw ContainerException::fromInterceptor(
                $interceptorClass,
                "Resolved interceptor must implement PreResolutionInterceptorInterface."
            );
        }

        return $instance;
    }
}
