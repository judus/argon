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
    /**
     * @api
     *
     * @param string $contextKey
     * @return self
     */
    public static function forMissingCompiledInvoker(string $contextKey): self
    {
        return new self(
            $contextKey,
            'compiled invoke',
            "No compiled invoker for '{$contextKey}' in strict no-reflection mode."
        );
    }

    public function __construct(
        string $serviceId,
        string $requestedBy = 'unknown',
        ?string $message = null
    ) {
        parent::__construct(
            $message ?? "Service '$serviceId' not found (requested by $requestedBy).",
            404
        );
    }
}
