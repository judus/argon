<?php

declare(strict_types=1);

namespace Maduser\Argon\Container\Support;

final class DebugTrace
{
    /**
     * @var array<string, array<string, array{expected: string, received?: string, error?: string, resolved?: array}>>
     */
    private static array $trace = [];

    public static function reset(): void
    {
        self::$trace = [];
    }

    public static function add(string $className, string $paramName, string $expectedType, mixed $value): void
    {
        self::$trace[$className][$paramName] = [
            'expected' => $expectedType,
            'received' => match (true) {
                is_object($value) => get_class($value),
                is_string($value) && class_exists($value) => $value . ' (class-string)',
                default => gettype($value)
            }
        ];
    }

    public static function fail(string $className, string $paramName, string $expectedType): void
    {
        self::$trace[$className][$paramName] = [
            'expected' => $expectedType,
            'error' => 'unresolved'
        ];
    }

    public static function nest(string $parentClass, string $paramName, array $subtrace): void
    {
        if (!isset(self::$trace[$parentClass][$paramName])) {
            self::$trace[$parentClass][$paramName] = [];
        }

        self::$trace[$parentClass][$paramName]['resolved'] = $subtrace;
    }

    public static function get(): array
    {
        return self::$trace;
    }

    public static function dump(): array
    {
        return self::$trace;
    }

    public static function hasErrors(): bool
    {
        foreach (self::$trace as $params) {
            foreach ($params as $info) {
                if (isset($info['error']) || ($info['received'] ?? null) === 'NULL') {
                    return true;
                }
            }
        }
        return false;
    }

    public static function toJson(): string
    {
        return json_encode(self::$trace, JSON_PRETTY_PRINT);
    }
}
