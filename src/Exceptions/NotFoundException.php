<?php

declare(strict_types=1);

namespace Maduser\Argon\Container\Exceptions;

use Exception;
use Maduser\Argon\Container\Support\DebugTrace;
use Psr\Container\NotFoundExceptionInterface;

/**
 * Thrown when a service cannot be found in the container.
 */
final class NotFoundException extends Exception implements NotFoundExceptionInterface
{
    public function __construct(string $serviceId, string $requestedBy = 'unknown')
    {
        $message = "Service '$serviceId' not found (requested by $requestedBy).";
        $trace = DebugTrace::toJson();

        if ($trace !== '{}') {
            $message .= "\n\nDebugTrace:\n$trace";
        }

        parent::__construct($message, 404);
    }
}
