<?php

declare(strict_types=1);

namespace Maduser\Argon\Container\Support;

final readonly class ArgumentResolutionStep
{
    public const string RUNTIME_ARGUMENT = 'runtime_argument';
    public const string BOUND_ARGUMENT = 'bound_argument';
    public const string CONTEXTUAL_SERVICE = 'contextual_service';
    public const string SERVICE = 'service';
    public const string DEFAULT_VALUE = 'default_value';
    public const string NULL_VALUE = 'null_value';
    public const string FAILURE = 'failure';
    public const string PRIMITIVE_FAILURE = 'primitive_failure';

    private function __construct(
        private string $kind,
        private mixed $value = null,
        private ?string $serviceId = null,
        private ?string $dependency = null,
        private ?string $message = null
    ) {
    }

    public static function runtimeArgument(): self
    {
        return new self(self::RUNTIME_ARGUMENT);
    }

    public static function boundArgument(mixed $value): self
    {
        return new self(self::BOUND_ARGUMENT, value: $value);
    }

    public static function contextualService(string $dependency, ?string $serviceId): self
    {
        return new self(self::CONTEXTUAL_SERVICE, serviceId: $serviceId, dependency: $dependency);
    }

    public static function service(string $serviceId): self
    {
        return new self(self::SERVICE, serviceId: $serviceId);
    }

    public static function defaultValue(mixed $value): self
    {
        return new self(self::DEFAULT_VALUE, value: $value);
    }

    public static function nullValue(): self
    {
        return new self(self::NULL_VALUE);
    }

    public static function failure(string $message): self
    {
        return new self(self::FAILURE, message: $message);
    }

    public static function primitiveFailure(): self
    {
        return new self(self::PRIMITIVE_FAILURE);
    }

    public function kind(): string
    {
        return $this->kind;
    }

    public function value(): mixed
    {
        return $this->value;
    }

    public function serviceId(): ?string
    {
        return $this->serviceId;
    }

    public function dependency(): ?string
    {
        return $this->dependency;
    }

    public function message(): ?string
    {
        return $this->message;
    }
}
