<?php

declare(strict_types=1);

namespace Maduser\Argon\Container\Interceptors\Pre;

use Maduser\Argon\Container\Contracts\PreResolutionInterceptorInterface;

/**
 * Just a conceptual example of a stub override interceptor.
 *
 * @noinspection PhpUndefinedClassInspection
 * @noinspection PhpUnhandledExceptionInspection
 * @noinspection PhpFullyQualifiedNameUsageInspection
 * @noinspection PhpUnused
 * @psalm-suppress UndefinedClass
 * @psalm-suppress InvalidReturnType
 * @psalm-suppress MissingDependency
 */
class StubOverrideInterceptor implements PreResolutionInterceptorInterface
{
    public static function supports(string|object $target): bool
    {
        return $target === PaymentGateway::class;
    }

    public function intercept(string $id, array &$parameters): ?object
    {
        return new class implements PaymentGateway {
            public function charge(int $amount): bool
            {
                return true; // no-op
            }
        };
    }
}
