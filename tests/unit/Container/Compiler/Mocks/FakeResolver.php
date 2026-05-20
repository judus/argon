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
                $fallback = "\$this->get('{$value}')";
            } else {
                $fallback = var_export($value, true);
            }

            return $this->runtimeArgumentExpression($argsVar, $name, $runtime, $fallback);
        }

        if ($typeName !== null && $this->contextualBindings->has($context, $typeName)) {
            $target = $this->contextualBindings->get($context, $typeName);
            $fallbacks[] = "\$this->get('{$target}')";
        }

        if ($typeName !== null && $this->container->has($typeName)) {
            $fallbacks[] = "\$this->get('{$typeName}')";
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
            return $this->runtimeArgumentExpression($argsVar, $name, $runtime, implode(' ?? ', $fallbacks));
        }

        return $this->runtimeArgumentExpression(
            $argsVar,
            $name,
            $runtime,
            'throw ContainerException::fromServiceId(' .
                var_export($serviceId, true) .
                ', ' .
                var_export("Missing required argument '$name'", true) .
                ')'
        );
    }

    private function runtimeArgumentExpression(
        string $argsVar,
        string $name,
        string $runtime,
        string $fallback
    ): string {
        return 'array_key_exists(' .
            var_export($name, true) .
            ", {$argsVar}) ? {$runtime} : {$fallback}";
    }
}
