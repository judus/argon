<?php

declare(strict_types=1);

namespace Maduser\Argon\Container\Exceptions;

use Exception;
use Psr\Container\NotFoundExceptionInterface;

class NotFoundException extends Exception implements NotFoundExceptionInterface
{
    private string $serviceId;

    // Constructor or a static factory for this exception
    public static function forService(string $id): self
    {
        $exception = new self("Service '$id' not found.");
        $exception->serviceId = $id;

        return $exception;
    }

    // Get the service that was not found
    public function getServiceId(): string
    {
        return $this->serviceId;
    }
}
