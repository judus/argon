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
use ReflectionException;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionType;
use ReflectionUnionType;
use RuntimeException;

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
     * @param ReflectionParameter  $param
     * @param array<string, mixed> $overrides
     *
     * @return mixed
     *
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function resolve(ReflectionParameter $param, array $overrides = []): mixed
    {
        $className = $param->getDeclaringClass()?->getName() ?? 'unknown class';
        $paramName = $param->getName();

        $merged = array_merge($this->registry->getScope($className), $overrides);

        if (array_key_exists($paramName, $merged)) {
            return $merged[$paramName];
        }

        return $this->resolveByType($param, $className, $paramName);
    }

    /**
     * @param ReflectionParameter $param
     * @param string              $className
     * @param string              $paramName
     *
     * @return mixed
     *
     * @throws ContainerException
     * @throws NotFoundException
     */
    private function resolveByType(ReflectionParameter $param, string $className, string $paramName): mixed
    {
        $type = $param->getType();

        if (
            $type instanceof ReflectionNamedType
            && $type->getName() === 'mixed'
            && !$param->isDefaultValueAvailable()

            // mixed is allowed *only* if default or optional (PHP's reflection is flaky here)
            && !$param->isOptional()
        ) {
            throw ContainerException::fromServiceId(
                $className,
                sprintf(
                    "Cannot resolve parameter \$%s in %s::__construct(): " .
                    "parameter is of type 'mixed' with no default or nullability",
                    $paramName,
                    $className
                )
            );
        }

        if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
            return $this->resolveTypeName($type->getName(), $className);
        }

        if ($type instanceof ReflectionUnionType) {
            return $this->resolveUnionType($type, $className, $paramName);
        }

        if ($param->isDefaultValueAvailable()) {
            return $param->getDefaultValue();
        }

        if ($param->allowsNull()) {
            return null;
        }

        throw ContainerException::forUnresolvedPrimitive($className, $paramName);
    }

    /**
     * @throws ContainerException
     */
    private function resolveUnionType(ReflectionUnionType $type, string $className, string $paramName): mixed
    {
        $resolvableTypes = [];

        foreach ($type->getTypes() as $unionType) {
            if ($unionType instanceof ReflectionNamedType && !$unionType->isBuiltin()) {
                $typeName = $unionType->getName();

                if ($this->contextualBindings->has($className, $typeName)) {
                    $resolvableTypes[] = fn(): object => $this->contextualResolver->resolve($className, $typeName);
                } elseif (class_exists($typeName)) {
                    $resolvableTypes[] = fn(): object => $this->resolveTypeName($typeName, $className);
                }
            }
        }

        if (count($resolvableTypes) === 1) {
            return $resolvableTypes[0]();
        }

        $typeList = implode(', ', array_map(
            function (ReflectionType $t): string {
                return $t instanceof ReflectionNamedType ? $t->getName() : 'unknown';
            },
            $type->getTypes()
        ));

        throw ContainerException::fromServiceId(
            $className,
            sprintf(
                'Ambiguous union type for parameter $%s in %s::__construct(): [%s]',
                $paramName,
                $className,
                $typeList
            )
        );
    }

    /**
     * @throws ContainerException
     * @throws NotFoundException
     */
    private function resolveTypeName(string $typeName, string $className): object
    {
        if ($this->contextualBindings->has($className, $typeName)) {
            return $this->contextualResolver->resolve($className, $typeName);
        }

        if (!$this->serviceResolver) {
            throw new RuntimeException('ParameterResolver: missing ServiceResolver.');
        }

        return $this->serviceResolver->resolve($typeName);
    }
}
