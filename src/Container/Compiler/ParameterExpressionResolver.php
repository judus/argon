<?php

declare(strict_types=1);

namespace Maduser\Argon\Container\Compiler;

use Maduser\Argon\Container\ArgonContainer;
use Maduser\Argon\Container\Contracts\ContextualBindingsInterface;
use Maduser\Argon\Container\Exceptions\ContainerException;
use ReflectionClass;
use ReflectionException;
use ReflectionNamedType;
use ReflectionParameter;

final class ParameterExpressionResolver
{
    public function __construct(
        private readonly ArgonContainer $container,
        private readonly ContextualBindingsInterface $contextualBindings
    ) {
    }

    /**
     * @param class-string $class
     * @return list<string>
     *
     * @throws ReflectionException
     */
    public function resolveConstructorArguments(
        string $class,
        string $serviceId,
        string $argsVar = '$args'
    ): array {
        $reflectionClass = new ReflectionClass($class);
        $constructor = $reflectionClass->getConstructor();

        if (!$constructor) {
            return [];
        }

        $resolved = [];

        foreach ($constructor->getParameters() as $param) {
            $resolved[] = $this->resolveParameter($param, $serviceId, $argsVar);
        }

        return $resolved;
    }

    /**
     * Resolves a constructor parameter for code generation in the compiled container.
     *
     * @throws ContainerException
     */
    public function resolveParameter(
        ReflectionParameter $parameter,
        string $serviceId,
        string $argsVar = '$args'
    ): string {
        $name = $parameter->getName();
        $type = $parameter->getType();
        $typeName = $type instanceof ReflectionNamedType ? $type->getName() : null;

        $runtime = "{$argsVar}[" . var_export($name, true) . "]";
        $fallbacks = [];
        $declaringClass = $parameter->getDeclaringClass();
        $context = $declaringClass?->getName() ?? $serviceId;

        if ($typeName !== null && $this->contextualBindings->has($context, $typeName)) {
            $target = $this->contextualBindings->get($context, $typeName);
            if (is_string($target)) {
                $fallbacks[] = "\$this->get('{$target}')";
            }
        }

        if ($typeName !== null) {
            if ($this->container->has($typeName)) {
                $fallbacks[] = "\$this->get('{$typeName}')";
            } elseif (class_exists($typeName) && !$parameter->allowsNull()) {
                $fallbacks[] = "\$this->get('{$typeName}')";
            }
        }

        $descriptor = $this->container->getDescriptor($serviceId);
        if ($descriptor?->hasArgument($name)) {
            /** @var mixed $value */
            $value = $descriptor->getArgument($name);

            if (
                is_string($value) &&
                $type instanceof ReflectionNamedType &&
                !$type->isBuiltin() &&
                class_exists($value)
            ) {
                $fallbacks[] = "\$this->get('{$value}')";
            } else {
                $fallbacks[] = var_export($value, true);
            }
        }

        if ($parameter->isDefaultValueAvailable()) {
            $fallbacks[] = var_export($parameter->getDefaultValue(), true);
        }

        if (
            empty($fallbacks) &&
            $type instanceof ReflectionNamedType &&
            $type->allowsNull()
        ) {
            $fallbacks[] = 'null';
        }

        if (!empty($fallbacks)) {
            return $runtime . ' ?? ' . implode(' ?? ', $fallbacks);
        }

        return $runtime;
    }
}
