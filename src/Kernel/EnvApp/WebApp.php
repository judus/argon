<?php

namespace Maduser\Argon\Kernel\EnvApp;

use Maduser\Argon\Kernel\Kernel;
use Maduser\Http\Request;
use Maduser\Http\Response;

class WebApp extends Kernel
{
    public function boot(): void
    {
        // Register services like request, response, routing, etc.
        $this->provider->singleton('Request', Request::class);
        $this->provider->singleton('Response', Response::class);
        // Add any routing system or controllers here
    }

    /**
     * Handle incoming HTTP requests.
     */
    public function handle(?callable $callback = null): void
    {
        try {
            // Resolve the request and response services
            $request = $this->provider->resolve('Request');
            $response = $this->provider->resolve('Response');

            // Execute callback if provided (for middleware or hooks)
            $callback && $callback($request, $response);

            // Handle routing, or directly resolve the controller
            // Assuming $router is a service that matches routes to controllers
            $router = $this->provider->resolve('Router');
            $router->dispatch($request, $response);

            // Send the HTTP response back to the client
            $response->send();
        } catch (\Exception $e) {
            $this->getErrorHandler()->handleException($e);
        }
    }
}
