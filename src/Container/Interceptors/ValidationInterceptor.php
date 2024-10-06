<?php

declare(strict_types=1);

namespace Maduser\Argon\Container\Interceptors;

use Maduser\Argon\Container\Contracts\TypeInterceptorInterface;
use Maduser\Argon\Container\Contracts\ValidationInterface;

class ValidationInterceptor implements TypeInterceptorInterface
{
    public function supports(object $service): bool
    {
        // Apply this interceptor only to services that implement ValidationInterface
        return $service instanceof ValidationInterface;
    }

    public function intercept(object $service): void
    {
        // Automatically call validate() if the service supports validation
        $service->validate();
    }
}