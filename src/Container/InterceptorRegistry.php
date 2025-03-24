<?php

declare(strict_types=1);

namespace Maduser\Argon\Container;

use Maduser\Argon\Container\Contracts\TypeInterceptorInterface;
use Maduser\Argon\Container\Exceptions\ContainerException;

class InterceptorRegistry
{
    /**
     * @var array<class-string<TypeInterceptorInterface>>
     */
    private array $interceptors = [];

    /**
     * @return array<class-string<TypeInterceptorInterface>>
     */
    public function all(): array
    {
        return $this->interceptors;
    }

    /**
     * @param class-string<TypeInterceptorInterface> $interceptorClass
     * @return void
     * @throws ContainerException
     */
    public function register(string $interceptorClass): void
    {
        if (!class_exists($interceptorClass)) {
            throw new ContainerException("Interceptor class '$interceptorClass' does not exist.");
        }

        if (!is_subclass_of($interceptorClass, TypeInterceptorInterface::class)) {
            throw new ContainerException("Interceptor '$interceptorClass' must implement TypeInterceptorInterface.");
        }

        $this->interceptors[] = $interceptorClass;
    }

    /**
     * Apply registered type-specific interceptors to the resolved instance.
     *
     * @param object $instance The resolved service instance.
     * @return object The intercepted instance.
     */
    public function apply(object $instance): object
    {
        foreach ($this->interceptors as $interceptorClass) {
            if ($interceptorClass::supports($instance)) {
                $interceptor = new $interceptorClass();
                $interceptor->intercept($instance);
            }
        }

        return $instance;
    }
}
