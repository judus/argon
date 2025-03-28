<?php

declare(strict_types=1);

namespace Tests\Integration\Mocks;

use Maduser\Argon\Container\Contracts\PreResolutionInterceptorInterface;
use Tests\Integration\Mocks\SimpleService;

final class ShortCircuitInterceptor implements PreResolutionInterceptorInterface
{
    public static function supports(object|string $target): bool
    {
        return $target === SimpleService::class;
    }

    public function intercept(string $id, array &$parameters): ?object
    {
        return new SimpleService('intercepted-instance');
    }
}
