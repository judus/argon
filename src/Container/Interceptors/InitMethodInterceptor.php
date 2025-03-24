<?php

declare(strict_types=1);

namespace Maduser\Argon\Container\Interceptors;

use Maduser\Argon\Container\Contracts\TypeInterceptorInterface;

class InitMethodInterceptor implements TypeInterceptorInterface
{
    private string $methodName;

    public function __construct(string $methodName = 'init')
    {
        $this->methodName = $methodName;
    }

    public static function supports(object|string $target): bool
    {
        return method_exists($target, 'init');
    }

    public function intercept(object $instance): void
    {
        $instance->{$this->methodName}();
    }
}