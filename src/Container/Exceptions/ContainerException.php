<?php

declare(strict_types=1);

namespace Maduser\Argon\Container\Exceptions;

use Exception;
use Maduser\Argon\Container\Support\DebugTrace;
use Psr\Container\ContainerExceptionInterface;
use Throwable;

/**
 * Represents a generic container exception.
 */
final class ContainerException extends Exception implements ContainerExceptionInterface
{
    public static function fromServiceId(string $id, string $message): self
    {
        return new self("Error with service '$id': $message" . self::appendTrace());
    }

    public static function forNonInstantiableClass(string $serviceId, string $className): self
    {
        return new self(
            "Class '$className' (requested as '$serviceId') is not instantiable." . self::appendTrace()
        );
    }

    public static function forUnresolvedPrimitive(string $className, string $paramName): self
    {
        return new self(
            "Cannot resolve primitive parameter '$paramName' in service '$className'." . self::appendTrace()
        );
    }

    /**
     * @param string $serviceId
     * @param array<array-key, string> $dependencyChain
     * @return self
     */
    public static function forCircularDependency(string $serviceId, array $dependencyChain): self
    {
        $chain = implode(' -> ', $dependencyChain);
        return new self(
            "Circular dependency detected for service '$serviceId'. Chain: $chain" . self::appendTrace()
        );
    }

    public static function forUnresolvableDependency(string $className, string $paramName): self
    {
        return new self(
            "Unresolvable dependency '$paramName' in service '$className'." . self::appendTrace()
        );
    }

    public static function forInstantiationFailure(string $className, Throwable $previous): self
    {
        return new self(
            "Failed to instantiate '$className' with resolved dependencies." . self::appendTrace(),
            0,
            $previous
        );
    }

    public static function fromInterceptor(string $interceptor, string $message): self
    {
        return new self("[$interceptor] $message" . self::appendTrace());
    }

    public static function fromInternalError(string $message): self
    {
        return new self($message . self::appendTrace());
    }

    private static function appendTrace(): string
    {
        $trace = DebugTrace::toJson();
        return $trace !== '{}' ? "\n\nDebugTrace:\n$trace" : '';
    }
}
