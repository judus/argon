<?php
declare(strict_types=1);

namespace Maduser\Argon\Container\Exceptions;

use Psr\Container\ContainerExceptionInterface;

class CircularDependencyException extends ContainerException implements ContainerExceptionInterface
{
    private array $dependencyChain;

    public static function forService(string $id, array $chain): self
    {
        $exception = new self("Circular dependency detected for service '$id'.");
        $exception->dependencyChain = $chain;

        return $exception;
    }

    public function getDependencyChain(): array
    {
        return $this->dependencyChain;
    }
}