<?php

declare(strict_types=1);

namespace Maduser\Argon\Container\Support;

use Maduser\Argon\Container\Exceptions\ContainerException;

final class StringHelper
{
    public static function invokeServiceMethod(string $serviceId, string $method = '__invoke'): string
    {
        $sanitizedService = self::sanitizeIdentifier($serviceId);
        $sanitizedMethod  = self::sanitizeIdentifier($method);

        return 'invoke_' . $sanitizedService . '__' . $sanitizedMethod;
    }

    public static function sanitizeIdentifier(string $value): string
    {
        $sanitized = preg_replace('/[^A-Za-z0-9_]/', '_', $value);

        if ($sanitized === null) {
            throw ContainerException::fromInternalError('Failed to sanitize identifier: ' . $value);
        }

        return $sanitized;
    }
}
