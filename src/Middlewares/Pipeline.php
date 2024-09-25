<?php

namespace Maduser\Argon\Middlewares;

use Closure;

class Pipeline
{
    /**
     * @var array The list of middlewares
     */
    private array $middlewares = [];

    /**
     * Add a middleware to the pipeline.
     *
     * @param Middleware|callable $middleware
     *
     * @return $this
     */
    public function addMiddleware(Middleware|callable $middleware): self
    {
        $this->middlewares[] = $middleware;

        return $this;
    }

    /**
     * Process the pipeline, passing the payload through all middlewares.
     *
     * @param mixed        $payload  The payload to pass through the pipeline
     * @param Closure|null $callback An optional callback to execute after the pipeline
     *
     * @return mixed
     */
    public function process(mixed $payload = null, ?Closure $callback = null): mixed
    {
        // Create a pipeline by reducing the middlewares stack.
        $pipeline = array_reduce(
            array_reverse($this->middlewares),
            fn($next, $middleware) => fn($payload) => $middleware->handle($payload, $next),
            $callback ?? fn($payload) => $payload // Fallback to a default callback if none is provided
        );

        // Execute the pipeline
        return $pipeline($payload);
    }
}
