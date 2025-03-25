<?php

declare(strict_types=1);

namespace Maduser\Argon\Container\Exceptions;

use Exception;
use Psr\Container\NotFoundExceptionInterface;

/**
 * Thrown when a service cannot be found in the container.
 */
final class NotFoundException extends Exception implements NotFoundExceptionInterface
{
    public function __construct(
        private readonly string $serviceId
    ) {
        parent::__construct("Service '$serviceId' not found.", 404);
    }

    public function getServiceId(): string
    {
        return $this->serviceId;
    }

    /**
     * @deprecated Just use `new NotFoundException($id)` directly.
     */
    public static function forService(string $id): self
    {
        return new self($id);
    }
}
