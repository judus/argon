<?php

declare(strict_types=1);

namespace Maduser\Argon\Container;

use Maduser\Argon\Container\Contracts\ArgumentMapInterface;
use Maduser\Argon\Container\Contracts\ContextualBindingsInterface;
use Maduser\Argon\Container\Contracts\ContextualResolverInterface;
use Maduser\Argon\Container\Contracts\ArgumentResolverInterface;
use Maduser\Argon\Container\Contracts\ServiceResolverInterface;
use Maduser\Argon\Container\Exceptions\ContainerException;
use Maduser\Argon\Container\Exceptions\NotFoundException;
use Maduser\Argon\Container\Support\DebugTrace;
use Override;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionType;
use ReflectionUnionType;

/**
 * Resolves constructor and method parameters with contextual or container-based resolution.
 */
final class ArgumentResolver implements ArgumentResolverInterface
{
    private ?ServiceResolverInterface $serviceResolver = null;

    public function __construct(
        private readonly ContextualResolverInterface $contextualResolver,
        private readonly ArgumentMapInterface $arguments,
        private readonly ContextualBindingsInterface $contextualBindings
    ) {
    }

    #[Override]
    public function setServiceResolver(ServiceResolverInterface $resolver): void
    {
        $this->serviceResolver = $resolver;
    }

    /**
     * @param ReflectionParameter $param
     * @param array<array-key, mixed> $overrides
     * @param string|null $contextId
     * @return mixed
     *
     * @throws ContainerException
     * @throws NotFoundException
     */
    #[Override]
    public function resolve(
        ReflectionParameter $param,
        array $overrides = [],
        ?string $contextId = null
    ): mixed {
        $context = $contextId ?? $param->getDeclaringClass()?->getName() ?? 'global';
        $paramName = $param->getName();

        $merged = array_merge($this->arguments->get($context), $overrides);

        $paramType = $param->getType();
        $expectedType = $paramType instanceof ReflectionNamedType ? $paramType->getName() : 'mixed';

        if (array_key_exists($paramName, $merged)) {
            /** @var null|bool|int|float|string|array|object $value */
            $value = $merged[$paramName];
            DebugTrace::add(
                $context,
                $paramName,
                $expectedType,
                is_scalar($value) ? (string) $value : gettype($value)
            );

            if (
                $paramType instanceof ReflectionNamedType &&
                !$paramType->isBuiltin() &&
                is_string($value) &&
                is_a($value, $paramType->getName(), true)
            ) {
                return $this->resolveTypeName($value, $context, $paramName, $expectedType);
            }

            return $value;
        }

        return $this->resolveByType($param, $context, $paramName);
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
            DebugTrace::fail($className, $paramName, 'mixed');
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
            $typeName = $type->getName();

            if (
                $param->allowsNull()
                && !$this->contextualBindings->has($className, $typeName)
                && !$this->arguments->has($className, $paramName)
            ) {
                DebugTrace::add($className, $paramName, $typeName, null);
                return null;
            }

            return $this->resolveTypeName($typeName, $className, $paramName, $typeName);
        }

        if ($type instanceof ReflectionUnionType) {
            return $this->resolveUnionType($type, $className, $paramName);
        }

        if ($param->isDefaultValueAvailable()) {
            /** @var null|bool|int|float|string|object|array $value */
            $value = $param->getDefaultValue();
            DebugTrace::add(
                $className,
                $paramName,
                $type instanceof ReflectionNamedType ? $type->getName() : 'mixed',
                is_scalar($value) ? (string) $value : gettype($value)
            );
            return $value;
        }

        if ($param->allowsNull()) {
            DebugTrace::add(
                $className,
                $paramName,
                $type instanceof ReflectionNamedType ? $type->getName() : 'mixed',
                null
            );
            return null;
        }

        DebugTrace::fail(
            $className,
            $paramName,
            $type instanceof ReflectionNamedType ? $type->getName() : 'mixed'
        );
        throw ContainerException::forUnresolvedPrimitive($className, $paramName);
    }

    /**
     * @throws ContainerException
     */
    private function resolveUnionType(ReflectionUnionType $type, string $className, string $paramName): object
    {
        $userDefined = [];

        foreach ($type->getTypes() as $unionType) {
            if ($unionType instanceof ReflectionNamedType && !$unionType->isBuiltin()) {
                $typeName = $unionType->getName();

                if ($this->contextualBindings->has($className, $typeName)) {
                    $userDefined[] = fn(): object => $this->contextualResolver->resolve($className, $typeName);
                }
            }
        }

        if (count($userDefined) === 1) {
            return $userDefined[0]();
        }

        $typeList = implode(', ', array_map(
            fn(ReflectionNamedType $t): string => $t->getName(),
            array_filter(
                $type->getTypes(),
                fn(ReflectionType $t): bool => $t instanceof ReflectionNamedType
            )
        ));

        DebugTrace::fail($className, $paramName, $typeList);

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
     * @template TGet of object
     * @param string $serviceId
     * @param class-string<TGet>|string $className
     * @param string|null $paramName
     * @param string|null $expectedType
     * @return object
     * @throws ContainerException
     * @throws NotFoundException
     */
    private function resolveTypeName(
        string $serviceId,
        string $className,
        ?string $paramName = null,
        ?string $expectedType = null
    ): object {
        $snapshot = DebugTrace::snapshot();
        if ($this->contextualBindings->has($className, $serviceId)) {
            $instance = $this->contextualResolver->resolve($className, $serviceId);
        } else {
            if ($this->serviceResolver === null) {
                throw ContainerException::fromServiceId($serviceId, 'ParameterResolver: missing ServiceResolver.');
            }

            $instance = $this->serviceResolver->resolve($serviceId);
        }
        $nestedTrace = DebugTrace::diff($snapshot);

        if ($paramName !== null) {
            $type = $expectedType ?? $serviceId;
            DebugTrace::add($className, $paramName, $type, $instance);

            if ($nestedTrace !== []) {
                DebugTrace::nest($className, $paramName, $nestedTrace);
            }
        }

        return $instance;
    }
}
