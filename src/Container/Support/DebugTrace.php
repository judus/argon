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

    /**
     * Returns a snapshot of the current trace state.
     *
     * @return array<string, array<string, array{expected: string, received?: string, error?: string, resolved?: array}>>
     */
    public static function snapshot(): array
    {
        return self::$trace;
    }

    /**
     * Computes the diff between the current trace and a previous snapshot.
     *
     * @param array<string, array<string, array{expected: string, received?: string, error?: string, resolved?: array>>> $previous
     * @return array<string, array<string, array{expected?: string, received?: string, error?: string, resolved?: array}>>
     */
    public static function diff(array $previous): array
    {
        return self::diffRecursive(self::$trace, $previous);
    }

    /**
     * @param array<string, mixed> $current
     * @param array<string, mixed> $previous
     * @return array<string, mixed>
     */
    private static function diffRecursive(array $current, array $previous): array
    {
        $diff = [];

        foreach ($current as $key => $value) {
            if (!array_key_exists($key, $previous)) {
                $diff[$key] = $value;
                continue;
            }

            $prevValue = $previous[$key];

            if (is_array($value) && is_array($prevValue)) {
                $nested = self::diffRecursive($value, $prevValue);

                if ($nested !== []) {
                    $diff[$key] = $nested;
                }

                continue;
            }

            if ($value !== $prevValue) {
                $diff[$key] = $value;
            }
        }

        return $diff;
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
