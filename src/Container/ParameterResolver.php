<?php

declare(strict_types=1);

namespace Maduser\Argon\Container;

use Maduser\Argon\Container\Contracts\ContextualBindingsInterface;
use Maduser\Argon\Container\Contracts\ContextualResolverInterface;
use Maduser\Argon\Container\Contracts\ParameterRegistryInterface;
use Maduser\Argon\Container\Contracts\ParameterResolverInterface;
use Maduser\Argon\Container\Contracts\ServiceResolverInterface;
use Maduser\Argon\Container\Exceptions\ContainerException;
use Maduser\Argon\Container\Exceptions\NotFoundException;
use ReflectionNamedType;
use ReflectionParameter;

/**
 * Resolves constructor and method parameters with contextual or container-based resolution.
 */
final class ParameterResolver implements ParameterResolverInterface
{
    private ?ServiceResolverInterface $serviceResolver = null;

    public function __construct(
        private readonly ContextualResolverInterface $contextualResolver,
        private readonly ParameterRegistryInterface $registry,
        private readonly ContextualBindingsInterface $contextualBindings
    ) {
    }

    public function setServiceResolver(ServiceResolverInterface $resolver): void
    {
        $this->serviceResolver = $resolver;
    }

    /**
     * @param ReflectionParameter $param
     * @param array<string, mixed> $overrides
     * @return mixed
     *
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function resolve(ReflectionParameter $param, array $overrides = []): mixed
    {
        $className = $param->getDeclaringClass()?->getName() ?? 'unknown class';
        $paramName = $param->getName();

        $merged = array_merge($this->registry->get($className), $overrides);

        if (array_key_exists($paramName, $merged)) {
            return $merged[$paramName];
        }

        return $this->resolveByType($param, $className, $paramName);
    }

    /**
     * @param ReflectionParameter $param
     * @param string $className
     * @param string $paramName
     * @return mixed
     *
     * @throws ContainerException
     * @throws NotFoundException
     */
    private function resolveByType(ReflectionParameter $param, string $className, string $paramName): mixed
    {
        $type = $param->getType();

        if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
            $typeName = $type->getName();

            if ($this->contextualBindings->has($className, $typeName)) {
                return $this->contextualResolver->resolve($className, $typeName);
            }

            if (!$this->serviceResolver) {
                throw new \RuntimeException("ParameterResolver: missing ServiceResolver.");
            }

            return $this->serviceResolver->resolve($typeName);
        }

        if ($param->isOptional()) {
            return $param->getDefaultValue();
        }

        throw ContainerException::forUnresolvedPrimitive($className, $paramName);
    }
}
