<?php

declare(strict_types=1);

namespace Maduser\Argon\Container\Interceptors\Pre;

use Maduser\Argon\Container\Contracts\PreResolutionInterceptorInterface;
use Maduser\Argon\Container\Exceptions\ContainerException;
use Maduser\Argon\Container\Exceptions\NotFoundException;
use Maduser\Argon\Container\ServiceContainer;
use RuntimeException;

/**
 * Just a conceptual example of a route-based repository interceptor.
 *
 * @noinspection PhpUndefinedClassInspection
 * @noinspection PhpUnhandledExceptionInspection
 * @noinspection PhpFullyQualifiedNameUsageInspection
 * @noinspection PhpUnused
 * @psalm-suppress UndefinedClass
 * @psalm-suppress InvalidReturnType
 * @psalm-suppress MissingDependency
 */
readonly class RouteBasedRepositoryInterceptor implements PreResolutionInterceptorInterface
{
    public function __construct(private Router $router, private ServiceContainer $container)
    {
    }

    public static function supports(string|object $target): bool
    {
        return $target === Repository::class;
    }

    /**
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function intercept(string $id, array &$parameters): ?object
    {
        $alias = $this->router->currentRoute()->segment(1);

        if ($this->container->has($alias)) {
            return $this->container->get($alias);
        }

        throw new RuntimeException("No service registered for route: $alias");
    }
}
