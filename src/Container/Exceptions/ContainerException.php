<?php

declare(strict_types=1);

namespace Maduser\Argon\Container\Exceptions;

use Exception;
use Psr\Container\ContainerExceptionInterface;
use Throwable;

/**
 * Represents a generic container exception.
 */
final class ContainerException extends Exception implements ContainerExceptionInterface
{
    public function __construct(
        string $message,
        ?string $serviceId = null,
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($message);
    }

    public static function fromServiceId(string $id, string $message): self
    {
        return new self("Error with service '$id': $message", $id);
    }

    public static function forNonInstantiableClass(string $serviceId, string $className): self
    {
        return new self(
            "Class '$className' (requested as '$serviceId') is not instantiable.",
            $serviceId
        );
    }

    public static function forUnresolvedPrimitive(string $className, string $paramName): self
    {
        return new self(
            "Cannot resolve primitive parameter '$paramName' in service '$className'.",
            $className
        );
    }

    public static function forCircularDependency(string $serviceId, array $dependencyChain): self
    {
        $chain = implode(' -> ', $dependencyChain);
        return new self(
            "Circular dependency detected for service '$serviceId'. Chain: $chain",
            $serviceId
        );
    }

    public static function forUnresolvableDependency(string $className, string $paramName): self
    {
        return new self(
            "Unresolvable dependency '$paramName' in service '$className'.",
            $className
        );
    }

    public static function forInstantiationFailure(string $className, \Throwable $previous): self
    {
        return new self(
            "Failed to instantiate '$className' with resolved dependencies.",
            $className,
            0,
            $previous
        );
    }

    public static function fromInterceptor(string $interceptor, string $message): self
    {
        return new self("[$interceptor] $message");
    }
}
