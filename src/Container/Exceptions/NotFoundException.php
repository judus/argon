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
    public function __construct(string $serviceId)
    {
        parent::__construct("Service '$serviceId' not found.", 404);
    }
}
