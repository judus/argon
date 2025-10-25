<?php

declare(strict_types=1);

namespace Maduser\Argon\Container\Support;

use Maduser\Argon\Container\Exceptions\ContainerException;
use function array_filter;
use function is_array;

final class DebugTrace
{
    /**
     * @var array<string, array<string, TraceEntry>>
     */
    private static array $trace = [];

    public static function reset(): void
    {
        self::$trace = [];
    }

    public static function add(string $className, string $paramName, string $expectedType, mixed $value): void
    {
        self::$trace[$className][$paramName] = TraceEntry::forValue($expectedType, $value);
    }

    public static function fail(string $className, string $paramName, string $expectedType): void
    {
        self::$trace[$className][$paramName] = TraceEntry::unresolved($expectedType);
    }

    public static function nest(string $parentClass, string $paramName, array $subtrace): void
    {
        if (!isset(self::$trace[$parentClass][$paramName])) {
            self::$trace[$parentClass][$paramName] = TraceEntry::empty();
        }

        if (self::looksLikeTraceMap($subtrace)) {
            self::$trace[$parentClass][$paramName]->setResolved(self::importTraceMap($subtrace));
        } else {
            self::$trace[$parentClass][$paramName]->setResolvedRaw($subtrace);
        }
    }

    public static function get(): array
    {
        return self::exportTraceMap(self::$trace);
    }

    public static function dump(): array
    {
        return self::exportTraceMap(self::$trace);
    }

    /**
     * Returns a snapshot of the current trace state.
     *
     * @return array<string, array<string, array<array-key, mixed>>>
     */
    public static function snapshot(): array
    {
        return self::exportTraceMap(self::$trace);
    }

    /**
     * Computes the diff between the current trace and a previous snapshot.
     *
     * @param array<array-key, mixed> $previous
     * @return array<string, array<string, array<array-key, mixed>>>
     */
    public static function diff(array $previous): array
    {
        $previousTrace = self::importTraceMap($previous);
        $diffEntries = self::diffTraceMaps(self::$trace, $previousTrace);

        return self::exportTraceMap($diffEntries);
    }

    public static function hasErrors(): bool
    {
        foreach (self::$trace as $params) {
            foreach ($params as $entry) {
                if ($entry->hasErrors()) {
                    return true;
                }
            }
        }
        return false;
    }

    public static function toJson(): string
    {
        $encoded = json_encode(self::exportTraceMap(self::$trace), JSON_PRETTY_PRINT);

        if ($encoded === false) {
            throw ContainerException::fromInternalError('Failed to encode debug trace to JSON.');
        }

        return $encoded;
    }

    /**
     * @param array<string, array<string, TraceEntry>> $map
     * @return array<string, array<string, array<array-key, mixed>>>
     */
    private static function exportTraceMap(array $map): array
    {
        $exported = [];

        foreach ($map as $class => $paramEntries) {
            foreach ($paramEntries as $param => $entry) {
                $exported[$class][$param] = $entry->toArray();
            }
        }

        return $exported;
    }

    /**
     * @param array<array-key, mixed> $data
     * @return array<string, array<string, TraceEntry>>
     */
    private static function importTraceMap(array $data): array
    {
        $map = [];

        foreach ($data as $class => $params) {
            if (!is_array($params)) {
                continue;
            }

            /** @var array<array-key, array<array-key, mixed>> $params */
            $params = $params;

            foreach ($params as $param => $entry) {
                $map[(string) $class][(string) $param] = TraceEntry::fromArray($entry);
            }
        }

        return $map;
    }

    private static function looksLikeTraceMap(array $data): bool
    {
        if ($data === []) {
            return false;
        }

        foreach ($data as $params) {
            if (!is_array($params)) {
                return false;
            }

            $arraysOnly = array_filter(
                $params,
                static fn($value): bool => is_array($value)
            );

            if ($arraysOnly === [] || count($arraysOnly) !== count($params)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string, array<string, TraceEntry>> $current
     * @param array<string, array<string, TraceEntry>> $previous
     * @return array<string, array<string, TraceEntry>>
     */
    private static function diffTraceMaps(array $current, array $previous): array
    {
        $diff = [];

        foreach ($current as $class => $paramEntries) {
            foreach ($paramEntries as $param => $entry) {
                $previousEntry = $previous[$class][$param] ?? null;

                if ($previousEntry === null) {
                    $diff[$class][$param] = $entry;
                    continue;
                }

                $entryDiff = $entry->diff($previousEntry);
                if ($entryDiff !== null) {
                    $diff[$class][$param] = $entryDiff;
                }
            }
        }

        return $diff;
    }
}
