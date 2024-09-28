<?php

namespace Maduser\Argon\Middlewares;

use Closure;

class AuthMiddleware implements Middleware
{
    /**
     * @param mixed        $payload
     * @param Closure $next
     *
     * @return mixed
     */
    public function handle(mixed $payload, \Closure $next): mixed
    {
        echo "Auth check before operation\n";

        // Suppress Psalm warning for now
        /** @psalm-suppress DocblockTypeContradiction */
        if (!$this->isAuthorized()) {
            echo "Unauthorized access. Terminating pipeline.\n";

            return null; // Stop the pipeline if unauthorized
        }

        // Proceed to the next middleware or the main operation
        $result = $next($payload);

        echo "Auth check after operation\n";

        return $result;
    }

    /**
     * @return true
     */
    private function isAuthorized(): bool
    {
        // Custom logic for authorization
        return true; // or false if unauthorized
    }
}
