<?php

declare(strict_types=1);

namespace Tests\Unit\Container\Stubs;

use Maduser\Argon\Container\Contracts\PreResolutionInterceptorInterface;

class StubPreInterceptor implements PreResolutionInterceptorInterface
{
    public static function supports(string|object $target): bool
    {
        return $target === 'StubMatch';
    }

    public function intercept(string $id, array &$parameters): ?object
    {
        $parameters['injectedByPre'] = true;
        return null;
    }
}
