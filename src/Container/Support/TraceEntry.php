<?php

declare(strict_types=1);

namespace Maduser\Argon\Container\Support;

use function array_key_exists;
use function is_array;

/**
 * @psalm-type TraceMap = array<string, array<string, TraceEntry>>
 */
final class TraceEntry
{
    private string $expected;
    private ?string $received;
    private ?string $error;

    /** @var TraceMap */
    private array $resolved;

    /** @var array<array-key, mixed> */
    private array $resolvedRaw;

    /**
     * @param TraceMap                 $resolved
     * @param array<array-key, mixed> $resolvedRaw
     */
    private function __construct(
        string $expected,
        ?string $received = null,
        ?string $error = null,
        array $resolved = [],
        array $resolvedRaw = []
    ) {
        $this->expected = $expected;
        $this->received = $received;
        $this->error = $error;
        $this->resolved = $resolved;
        $this->resolvedRaw = $resolvedRaw;
    }

    public static function forValue(string $expected, mixed $value): self
    {
        $received = match (true) {
            is_object($value) => get_class($value),
            is_string($value) && class_exists($value) => $value . ' (class-string)',
            default => gettype($value),
        };

        return new self($expected, $received);
    }

    public static function unresolved(string $expected): self
    {
        return new self($expected, null, 'unresolved');
    }

    public static function empty(string $expected = 'unknown'): self
    {
        return new self($expected);
    }

    /**
     * @param TraceMap $resolved
     */
    public function setResolved(array $resolved): void
    {
        $this->resolved = $resolved;
        $this->resolvedRaw = [];
    }

    /**
     * @return TraceMap
     */
    public function getResolved(): array
    {
        return $this->resolved;
    }

    /**
     * @param array<array-key, mixed> $resolved
     */
    public function setResolvedRaw(array $resolved): void
    {
        $this->resolved = [];
        $this->resolvedRaw = $resolved;
    }

    /**
     * @return array<array-key, mixed>
     */
    public function getResolvedRaw(): array
    {
        return $this->resolvedRaw;
    }

    public function getExpected(): string
    {
        return $this->expected;
    }

    public function getReceived(): ?string
    {
        return $this->received;
    }

    public function getError(): ?string
    {
        return $this->error;
    }

    public function hasErrors(): bool
    {
        if ($this->error !== null) {
            return true;
        }

        if ($this->received === 'NULL') {
            return true;
        }

        foreach ($this->resolved as $entries) {
            foreach ($entries as $entry) {
                if ($entry->hasErrors()) {
                    return true;
                }
            }
        }

        if ($this->resolvedRaw !== [] && $this->rawResolvedHasErrors($this->resolvedRaw)) {
            return true;
        }

        return false;
    }

    public function toArray(): array
    {
        $data = ['expected' => $this->expected];

        if ($this->received !== null) {
            $data['received'] = $this->received;
        }

        if ($this->error !== null) {
            $data['error'] = $this->error;
        }

        if ($this->resolved !== []) {
            $resolved = $this->resolvedToArray();
            if ($resolved !== []) {
                $data['resolved'] = $resolved;
            }
        } elseif ($this->resolvedRaw !== []) {
            $data['resolved'] = $this->resolvedRaw;
        }

        return $data;
    }

    /**
     * @param TraceMap $current
     * @param TraceMap $previous
     * @return TraceMap
     */
    private static function diffResolved(array $current, array $previous): array
    {
        /** @var TraceMap $diff */
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

    public function diff(TraceEntry $previous): ?TraceEntry
    {
        $resolvedDiff = self::diffResolved($this->resolved, $previous->resolved);
        $rawDiff = self::diffRawResolved($this->resolvedRaw, $previous->resolvedRaw);

        if (
            $this->expected === $previous->expected
            && $this->received === $previous->received
            && $this->error === $previous->error
            && $resolvedDiff === []
            && $rawDiff === []
        ) {
            return null;
        }

        $diff = new self($this->expected, $this->received, $this->error);

        if ($resolvedDiff !== []) {
            $diff->setResolved($resolvedDiff);
        } elseif ($rawDiff !== []) {
            $diff->setResolvedRaw($rawDiff);
        }

        return $diff;
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $expected = isset($data['expected']) ? (string) $data['expected'] : 'unknown';
        $received = isset($data['received']) ? (string) $data['received'] : null;
        $error = isset($data['error']) ? (string) $data['error'] : null;

        $instance = new self($expected, $received, $error);

        if (isset($data['resolved']) && is_array($data['resolved'])) {
            $resolvedData = $data['resolved'];

            if (self::isTraceMapArray($resolvedData)) {
                $resolved = [];
                foreach ($resolvedData as $class => $paramEntries) {
                    if (!is_array($paramEntries)) {
                        continue;
                    }

                    $converted = [];

                    /** @psalm-suppress MixedAssignment */
                    foreach ($paramEntries as $param => $entry) {
                        if (!is_array($entry)) {
                            continue;
                        }

                        $converted[(string) $param] = self::fromArray($entry);
                    }

                    if ($converted !== []) {
                        $resolved[(string) $class] = $converted;
                    }
                }

                $instance->setResolved($resolved);
            } else {
                $instance->setResolvedRaw($resolvedData);
            }
        }

        return $instance;
    }

    /**
     * @return array<string, array<string, array<array-key, mixed>>>
     */
    private function resolvedToArray(): array
    {
        $exported = [];

        foreach ($this->resolved as $class => $paramEntries) {
            foreach ($paramEntries as $param => $entry) {
                $exported[$class][$param] = $entry->toArray();
            }
        }

        return $exported;
    }

    /**
     * @param array<array-key, mixed> $resolved
     */
    private function rawResolvedHasErrors(array $resolved): bool
    {
        foreach ($resolved as $value) {
            if (!is_array($value)) {
                continue;
            }

            if (isset($value['error'])) {
                return true;
            }

            if (($value['received'] ?? null) === 'NULL') {
                return true;
            }

            if ($this->rawResolvedHasErrors($value)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<array-key, mixed> $current
     * @param array<array-key, mixed> $previous
     * @return array<array-key, mixed>
     *
     * @psalm-suppress MixedAssignment
     * @psalm-suppress MixedArrayAccess
     */
    private static function diffRawResolved(array $current, array $previous): array
    {
        $diff = [];

        foreach ($current as $key => $value) {
            if (!array_key_exists($key, $previous)) {
                $diff[$key] = $value;
                continue;
            }

            $prev = $previous[$key];

            if (is_array($value) && is_array($prev)) {
                $nested = self::diffRawResolved($value, $prev);

                if ($nested !== []) {
                    $diff[$key] = $nested;
                }

                continue;
            }

            if ($value !== $prev) {
                $diff[$key] = $value;
            }
        }

        return $diff;
    }

    private static function isTraceMapArray(array $data): bool
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
}
