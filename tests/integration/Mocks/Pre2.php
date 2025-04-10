<?php

declare(strict_types=1);

namespace Tests\Integration\Mocks;

use Maduser\Argon\Container\Contracts\PreResolutionInterceptorInterface;
use Tests\Integration\Mocks\InterceptedClass;

final class Pre2 implements PreResolutionInterceptorInterface
{
    public static function supports(object|string $target): bool
    {
        return $target === InterceptedClass::class;
    }

    public function intercept(string $id, array &$parameters): ?object
    {
        $parameters['value'] = 'from-pre2'; // overrides pre1

        return null;
    }
}
