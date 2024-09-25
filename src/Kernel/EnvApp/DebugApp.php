<?php

namespace Maduser\Argon\Kernel\EnvApp;

use Maduser\Argon\Kernel\Kernel;
use Maduser\Debug\Debugger;

class DebugApp extends Kernel
{
    public function boot(): void
    {
        // Register services for debugging and profiling
        $this->provider->singleton('Debugger', Debugger::class);
    }

    public function handle(?callable $callback = null): void
    {
        try {
            /** @var Debugger $debugger */
            $debugger = $this->provider->resolve('Debugger');
            $debugger->start(); // Start debugging session

            $callback && $callback();

            $debugger->stop(); // Stop debugging session
            $debugger->report(); // Output debugging information

        } catch (\Exception $e) {
            $this->getErrorHandler()->handleException($e);
        }
    }
}
