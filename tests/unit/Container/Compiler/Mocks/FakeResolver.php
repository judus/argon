<?php

declare(strict_types=1);

namespace Tests\Unit\Container\Compiler\Mocks;

use ReflectionNamedType;
use ReflectionParameter;

final class FakeResolver
{
    public DummyContainer $container;
    public DummyBindings $contextualBindings;

    /**
     * @param  array<array-key, array<array-key, string>> $bindings
     */
    public function __construct(
        array $bindings = []
    ) {
        $this->container = new DummyContainer();

        $this->contextualBindings = new DummyBindings($bindings);
    }

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

        // Contextual binding
        if ($typeName !== null && $this->contextualBindings->has($context, $typeName)) {
            $target = $this->contextualBindings->get($context, $typeName);
            $fallbacks[] = "\$this->get('{$target}')";
        }

        // Registered service
        if ($typeName !== null && $this->container->has($typeName)) {
            $fallbacks[] = "\$this->get('{$typeName}')";
        }

        // Explicit descriptor argument
        $descriptor = $this->container->getDescriptor($serviceId);


        if ($descriptor !== null && $descriptor->hasArgument($name)) {
            /**
             * @var null|bool|int|float|string|array|object $value
             */
            $value = $this->container->getDescriptor($serviceId)?->getArgument($name);

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

        // Method default value
        if ($parameter->isDefaultValueAvailable()) {
            $fallbacks[] = var_export($parameter->getDefaultValue(), true);
        }

        // Fallback to null ONLY if no viable candidate exists and nullable
        if (
            empty($fallbacks) &&
            $type instanceof ReflectionNamedType &&
            $type->allowsNull()
        ) {
            $fallbacks[] = 'null';
        }

        // Final fallback chain
        if (!empty($fallbacks)) {
            return $runtime . ' ?? ' . implode(' ?? ', $fallbacks);
        }

        // No fallbacks—return raw
        return $runtime;
    }
}
