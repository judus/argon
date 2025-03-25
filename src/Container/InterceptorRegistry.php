<?php

declare(strict_types=1);

namespace Maduser\Argon\Container;

use Maduser\Argon\Container\Contracts\InterceptorInterface;
use Maduser\Argon\Container\Contracts\InterceptorRegistryInterface;
use Maduser\Argon\Container\Exceptions\ContainerException;

/**
 * Manages registration and execution of type interceptors.
 */
final class InterceptorRegistry implements InterceptorRegistryInterface
{
    /**
     * @var list<class-string<InterceptorInterface>>
     */
    private array $interceptors = [];

    /**
     * @return list<class-string<InterceptorInterface>>
     */
    public function all(): array
    {
        return $this->interceptors;
    }

    /**
     * @param class-string<InterceptorInterface> $interceptorClass
     * @throws ContainerException
     */
    public function register(string $interceptorClass): void
    {
        if (!class_exists($interceptorClass)) {
            throw new ContainerException("Interceptor class '$interceptorClass' does not exist.");
        }

        if (!is_subclass_of($interceptorClass, InterceptorInterface::class)) {
            throw new ContainerException("Interceptor '$interceptorClass' must implement TypeInterceptorInterface.");
        }

        $this->interceptors[] = $interceptorClass;
    }

    /**
     * Applies all supported interceptors to the given instance.
     *
     * @param object $instance
     * @return object
     */
    public function apply(object $instance): object
    {
        foreach ($this->interceptors as $interceptorClass) {
            if ($interceptorClass::supports($instance)) {
                (new $interceptorClass())->intercept($instance);
            }
        }

        return $instance;
    }
}
