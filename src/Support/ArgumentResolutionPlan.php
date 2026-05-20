<?php

declare(strict_types=1);

namespace Maduser\Argon\Container\Support;

final readonly class ArgumentResolutionPlan
{
    /**
     * @param list<ArgumentResolutionStep> $steps
     */
    public function __construct(
        private string $parameterName,
        private string $context,
        private string $serviceId,
        private string $expectedType,
        private ?string $namedTypeName,
        private bool $namedTypeIsBuiltin,
        private array $steps
    ) {
    }

    public function parameterName(): string
    {
        return $this->parameterName;
    }

    public function context(): string
    {
        return $this->context;
    }

    public function serviceId(): string
    {
        return $this->serviceId;
    }

    public function expectedType(): string
    {
        return $this->expectedType;
    }

    public function namedTypeName(): ?string
    {
        return $this->namedTypeName;
    }

    /**
     * @return list<ArgumentResolutionStep>
     */
    public function steps(): array
    {
        return $this->steps;
    }

    public function canResolveClassString(mixed $value): bool
    {
        return $this->resolveClassString($value) !== null;
    }

    /**
     * @return class-string|null
     */
    public function resolveClassString(mixed $value): ?string
    {
        if (!is_string($value) || !$this->canResolveClassStringParameter()) {
            return null;
        }

        $typeName = $this->namedTypeName;
        if (
            $typeName === null
            || (!class_exists($typeName) && !interface_exists($typeName))
            || (!class_exists($value) && !interface_exists($value))
            || !is_a($value, $typeName, true)
        ) {
            return null;
        }

        return $value;
    }

    public function canResolveClassStringParameter(): bool
    {
        return $this->namedTypeName !== null && !$this->namedTypeIsBuiltin;
    }
}
