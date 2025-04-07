<?php

declare(strict_types=1);

namespace Maduser\Argon\Container\Support;

class StringHelper
{
    public static function invokeServiceMethod(string $serviceId, string $method = '__invoke'): string
    {
        $sanitizedService = preg_replace('/[^A-Za-z0-9_]/', '_', $serviceId);
        $sanitizedMethod  = preg_replace('/[^A-Za-z0-9_]/', '_', $method);

        return 'invoke_' . $sanitizedService . '__' . $sanitizedMethod;
    }
}
