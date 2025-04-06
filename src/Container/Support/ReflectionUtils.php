<?php

declare(strict_types=1);

namespace Maduser\Argon\Container\Support;

use ReflectionException;
use ReflectionIntersectionType;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionUnionType;
use UnitEnum;
use Maduser\Argon\Container\Exceptions\ContainerException;

final class ReflectionUtils
{
    private const PRIMITIVES = ['int', 'float', 'bool', 'string', 'bool'];

    /**
     * Generates a compilable argument map for a class method.
     *
     * @param class-string|object $target
     * @param string $method
     * @return array<string, class-string|string|int|float|bool|null>
     * @throws ReflectionException|ContainerException
     */
    public static function getMethodParameters(string|object $target, string $method): array
    {
        $refMethod = new ReflectionMethod($target, $method);
        $params = [];

        foreach ($refMethod->getParameters() as $param) {
            if ($param->isVariadic()) {
                throw new ContainerException(
                    "Variadic parameter \${$param->getName()} in {$refMethod->getName()}() is not supported"
                );
            }

            $type = $param->getType();

            if ($type instanceof ReflectionIntersectionType) {
                throw new ContainerException(
                    "Intersection types are not supported for parameter \${$param->getName()} in {$refMethod->getName()}()"
                );
            }

            if ($type instanceof ReflectionUnionType) {
                $params[$param->getName()] = self::resolveUnionType($param, $type, $refMethod);
                continue;
            }

            if ($type instanceof ReflectionNamedType) {
                $params[$param->getName()] = self::resolveNamedType($param, $type, $refMethod);
                continue;
            }

            // No type? Fallback to default/null
            $params[$param->getName()] = self::resolveDefaultOrNull($param, $refMethod);
        }

        return $params;
    }

    /**
     * @throws ReflectionException
     * @throws ContainerException
     */
    private static function resolveUnionType(ReflectionParameter $param, ReflectionUnionType $type, ReflectionMethod $method): string|int|float|bool|null
    {
        $defaultAvailable = $param->isDefaultValueAvailable();
        $default = $defaultAvailable ? $param->getDefaultValue() : null;
        $types = $type->getTypes();

        // Scalar union with valid default (e.g. int|string $x = 42)
        $allScalars = array_reduce($types, fn ($carry, $t) => $carry && self::isPrimitive($t->getName()), true);

        if ($allScalars) {
            if (!$defaultAvailable || (!is_scalar($default) && $default !== null)) {
                throw new ContainerException(
                    "Union of scalar types for parameter \${$param->getName()} in {$method->getName()}() requires a scalar default"
                );
            }
            return $default;
        }

        // Look for a single resolvable class/interface
        $resolved = null;
        foreach ($types as $t) {
            $name = $t->getName();
            if (!self::isPrimitive($name) && (class_exists($name) || interface_exists($name))) {
                if ($resolved !== null) {
                    throw new ContainerException(
                        "Multiple class/interface types in union not supported for \${$param->getName()} in {$method->getName()}()"
                    );
                }
                $resolved = $name;
            }
        }

        if ($resolved !== null) {
            return '@' . $resolved;
        }

        if ($defaultAvailable && (is_scalar($default) || $default === null)) {
            return $default;
        }

        throw new ContainerException(
            "Unresolvable union type for parameter \${$param->getName()} in {$method->getName()}()"
        );
    }

    /**
     * @throws ContainerException
     */
    private static function resolveNamedType(ReflectionParameter $param, ReflectionNamedType $type, ReflectionMethod $method): string|int|float|bool|null
    {
        $typeName = $type->getName();

        if (self::isPrimitive($typeName)) {
            return self::resolveDefaultOrNull($param, $method);
        }

        if (in_array($typeName, ['callable', 'mixed'], true)) {
            return self::resolveDefaultOrNull($param, $method);
        }

        // ENUM SUPPORT
        if (enum_exists($typeName)) {
            if ($param->isDefaultValueAvailable()) {
                $default = $param->getDefaultValue();

                // Backed enums
                if (is_scalar($default)) {
                    foreach ($typeName::cases() as $case) {
                        if ($case instanceof \BackedEnum && $case->value === $default) {
                            return '@' . $typeName . '::' . $case->name;
                        }
                    }
                }

                // Pure enums (non-backed)
                foreach ($typeName::cases() as $case) {
                    if ($case === $default) {
                        return '@' . $typeName . '::' . $case->name;
                    }
                }

                throw new ContainerException("Failed to resolve enum default value for \${$param->getName()} in {$method->getName()}()");
            }

            return '@' . $typeName;
        }

        if (!class_exists($typeName) && !interface_exists($typeName)) {
            throw new ContainerException(
                "Unknown class or interface '$typeName' for \${$param->getName()} in {$method->getName()}()"
            );
        }

        // OBJECT OR ARRAY DEFAULTS NOT ALLOWED
        if ($param->isDefaultValueAvailable()) {
            $default = $param->getDefaultValue();

            if ($default === null && $type->allowsNull()) {
                return '@' . $typeName;
            }

            if (is_object($default)) {
                throw new ContainerException("Cannot compile object default for \${$param->getName()} in {$method->getName()}()");
            }

            if (is_array($default)) {
                throw new ContainerException("Array default not allowed for \${$param->getName()} in {$method->getName()}()");
            }

            if (is_scalar($default) || $default === null) {
                return $default;
            }

            throw new ContainerException("Unsupported default value type " . get_debug_type($default) . " for \${$param->getName()} in {$method->getName()}()");
        }

        return '@' . $typeName;
    }


    /**
     * @throws ContainerException
     */
    private static function resolveDefaultOrNull(ReflectionParameter $param, ReflectionMethod $method): string|int|float|bool|null
    {
        if ($param->isDefaultValueAvailable()) {
            $default = $param->getDefaultValue();

            if ($default instanceof \UnitEnum) {
                return get_class($default) . '::' . $default->name;
            }

            if (is_array($default)) {
                throw new ContainerException(
                    "Default array value not supported for \${$param->getName()} in {$method->getName()}()"
                );
            }

            if (is_object($default)) {
                throw new ContainerException(
                    "Object instance as default not supported for \${$param->getName()} in {$method->getName()}()"
                );
            }

            if (is_scalar($default) || $default === null) {
                return $default;
            }

            throw new ContainerException(
                "Unsupported default value type " . get_debug_type($default) .
                " for \${$param->getName()} in {$method->getName()}()"
            );
        }

        return null;
    }


    private static function isPrimitive(string $type): bool
    {
        return in_array($type, self::PRIMITIVES, true);
    }
}
