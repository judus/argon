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
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionType;
use ReflectionUnionType;
use RuntimeException;

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

    public function setServiceResolver(ServiceResolverInterface $resolver): void
    {
        $this->serviceResolver = $resolver;
    }

    /**
     * @param ReflectionParameter  $param
     * @param array<array-key, mixed> $overrides
     *
     * @return mixed
     *
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function resolve(
        ReflectionParameter $param,
        array $overrides = [],
        ?string $contextId = null
    ): mixed {
        $context = $contextId ?? $param->getDeclaringClass()?->getName() ?? 'global';
        $paramName = $param->getName();

        $merged = array_merge($this->arguments->get($context), $overrides);

        if (array_key_exists($paramName, $merged)) {
            /** @var null|bool|int|float|string|array|object $value */
            $value = $merged[$paramName];
            $paramType = $param->getType();

            DebugTrace::add(
                $context,
                $paramName,
                $paramType instanceof ReflectionNamedType ? $paramType->getName() : 'mixed',
                is_scalar($value) ? (string) $value : gettype($value)
            );

            if (
                $paramType instanceof ReflectionNamedType &&
                !$paramType->isBuiltin() &&
                is_string($value) &&
                is_a($value, $paramType->getName(), true)
            ) {
                return $this->resolveTypeName($value, $context);
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

            // 👇 If it's nullable and no contextual or argument binding exists, back off!
            if (
                $param->allowsNull()
                && !$this->contextualBindings->has($className, $typeName)
                && !$this->arguments->has($className, $paramName)
            ) {
                DebugTrace::add($className, $paramName, $typeName, null);
                return null;
            }

            return $this->resolveTypeName($typeName, $className);
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
        // TODO: I won't support this BS (yet), user shall make up his mind, ain't a sushi bar...
        // class MyClass {
        //     public function __construct(public StringableObject|string $string) {}
        // }

        $userDefined = [];

        foreach ($type->getTypes() as $unionType) {
            if ($unionType instanceof ReflectionNamedType && !$unionType->isBuiltin()) {
                $typeName = $unionType->getName();

                if ($this->contextualBindings->has($className, $typeName)) {
                    $userDefined[] = fn(): object => $this->contextualResolver->resolve($className, $typeName);
                }

                // See note below. This part of the BF. Don't do it! Not again! :)
                // If we have union type, meaning we have multiple classes, we'll always have an ambiguous situation
                // In a mixed situation, userDefined and fromAutoResolution, userDefined takes precedence anyway.
                // Try to unit test that, LMAO!
                // elseif (class_exists($typeName)) {
                //     $fromAutoResolution[] = fn(): object => $this->resolveTypeName($typeName, $className);
                // }
            }
        }

        // Prioritize contextual bindings
        if (count($userDefined) === 1) {
            return $userDefined[0]();
        }

        // Unless PHP allows type hinting non-existing classes, nothing gets executed.
        // Leave this note here, so I remember next time before I spent hours on this BF.
        //
        // If no $userDefined, fall back to auto resolution (<-- nope! Union type means greater than 1!)
        // if (count($userDefined) === 0 && count($fromAutoResolution) === 1) {
        //     return $fromAutoResolution[0]();
        // }

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
     * @param string $typeName
     * @param class-string<TGet>|string $className
     * @return object
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
