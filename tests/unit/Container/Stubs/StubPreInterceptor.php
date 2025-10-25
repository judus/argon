<?php

declare(strict_types=1);

namespace Tests\Unit\Container\Stubs;

use Maduser\Argon\Container\Contracts\PreResolutionInterceptorInterface;
use stdClass;

final class StubPreInterceptor implements PreResolutionInterceptorInterface
{
    #[\Override]
    public static function supports(object|string $target): bool
    {
        return $target === 'StubMatch';
    }

    #[\Override]
    public function intercept(string $id, array &$parameters = []): ?object
    {
        return new stdClass(); // or null, doesn’t matter for this test
    }
}
