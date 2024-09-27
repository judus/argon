<?php

namespace Maduser\Argon\Middlewares;

use Closure;

class AuthMiddleware implements Middleware
{
    public function handle($payload, \Closure $next): mixed
    {
        echo "Auth check before operation\n";

        // Perform authorization logic here
        if (!$this->isAuthorized()) {
            echo "Unauthorized access. Terminating pipeline.\n";

            return null; // Stop the pipeline if unauthorized
        }

        // Proceed to the next middleware or the main operation
        $result = $next($payload);

        echo "Auth check after operation\n";

        return $result;
    }

    private function isAuthorized(): bool
    {
        // Custom logic for authorization
        return true; // or false if unauthorized
    }
}
