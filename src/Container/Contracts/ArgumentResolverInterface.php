<?php

declare(strict_types=1);

namespace Maduser\Argon\Container\Contracts;

use Maduser\Argon\Container\Exceptions\ContainerException;
use Maduser\Argon\Container\Exceptions\NotFoundException;
use ReflectionParameter;

/**
 * Resolves constructor and method parameters with contextual or container-based resolution.
 */
interface ArgumentResolverInterface
{
    public function setServiceResolver(ServiceResolverInterface $resolver): void;

    /**
     * @param ReflectionParameter $param
     * @param array<string, mixed> $overrides
     * @return mixed
     *
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function resolve(ReflectionParameter $param, array $overrides = []): mixed;
}
