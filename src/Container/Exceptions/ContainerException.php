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
    private ?string $serviceId = null;

    public static function fromServiceId(string $id, string $message): self
    {
        $exception = new self("Error with service '$id': $message");
        $exception->serviceId = $id;

        return $exception;
    }

    public static function forNonInstantiableClass(string $serviceId, string $className): self
    {
        $message = "Class '$className' (requested as '$serviceId') is not instantiable.";
        $exception = new self($message);
        $exception->serviceId = $serviceId;

        return $exception;
    }

    public static function forUnresolvedPrimitive(string $className, string $paramName): self
    {
        return new self(
            "Cannot resolve primitive type parameter '{$paramName}' in service '{$className}'."
        );
    }

    public static function forCircularDependency(string $serviceId, array $dependencyChain): self
    {
        $chain = implode(' -> ', $dependencyChain);

        return new self("Circular dependency detected for service '$serviceId'. Chain: $chain");
    }

    public static function forUnresolvableDependency(string $className, string $paramName): self
    {
        return new self("Unresolvable dependency '{$paramName}' in service '{$className}'.");
    }

    public function getServiceId(): ?string
    {
        return $this->serviceId;
    }
}
