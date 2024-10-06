<?php

declare(strict_types=1);

namespace Maduser\Argon\Container\Exceptions;

use Exception;
use Psr\Container\ContainerExceptionInterface;

/**
 * Class ContainerException
 *
 * Represents an exception in the container.
 */
class ContainerException extends Exception implements ContainerExceptionInterface
{
    private string $serviceId;
    private string $details;

    public function getServiceId(): string
    {
        return $this->serviceId;
    }

    public static function fromServiceId(string $id, string $message): self
    {
        $exception = new self("Error with service '$id': $message");
        $exception->serviceId = $id;

        return $exception;
    }

    // For non-instantiable classes
    public static function forNonInstantiableClass(string $serviceId, string $className): self
    {
        // More detailed exception message
        $message = "Class '$className' (requested as '$serviceId') is not instantiable.";
        $exception = new self($message);
        $exception->serviceId = $serviceId;

        return $exception;
    }

    // For unresolved primitive types
    public static function forUnresolvedPrimitive(string $className, string $paramName): self
    {
        return new self(
            "Cannot resolve primitive type parameter '{$paramName}' in service '{$className}'."
        );
    }

    // For circular dependencies
    public static function forCircularDependency(string $serviceId, array $dependencyChain): self
    {
        // Join the chain to display it properly in the error message
        $chain = implode(' -> ', $dependencyChain);

        return new self("Circular dependency detected for service '$serviceId'. Chain: $chain");
    }

    // For other unresolvable dependencies
    public static function forUnresolvableDependency(string $className, string $paramName): self
    {
        return new self("Unresolvable dependency '{$paramName}' in service '{$className}'.");
    }
}


