<?php

namespace Maduser\Argon\Kernel\EnvApp;

use Maduser\Argon\Kernel\Kernel;

class EmbeddedApp extends Kernel
{
    public function boot(): void
    {
        // Register lightweight services specific to embedded systems.
    }

    public function handle(?callable $callback = null): void
    {
        // Handle embedded system-specific tasks, e.g., communicating with hardware.
        $callback && $callback();
    }
}
