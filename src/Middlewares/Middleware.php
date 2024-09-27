<?php

namespace Maduser\Argon\Middlewares;

interface Middleware
{
    /**
     * Handle the middleware action.
     *
     * @param mixed   $payload The payload being passed through the pipeline
     * @param Closure $next    The next middleware in the pipeline
     *
     * @return mixed
     */
    public function handle(mixed $payload, \Closure $next): mixed;
}
