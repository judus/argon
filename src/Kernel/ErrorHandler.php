<?php

declare(strict_types=1);

namespace Maduser\Argon\Kernel;

use Throwable;

class ErrorHandler
{
    protected Kernel $application;

    public function __construct(Kernel $application)
    {
        $this->application = $application;
    }

    public function register(): void
    {
        set_exception_handler([$this, 'handleException']);
        set_error_handler([$this, 'handleError']);
        register_shutdown_function([$this, 'handleShutdown']);
    }

    /**
     * Handle exceptions by delegating to the application's handler.
     *
     * @param Throwable $exception
     */
    public function handleException(Throwable $exception): void
    {
        $this->application->handleException($exception);
    }

    /**
     * Handle errors by delegating to the application's handler.
     *
     * @param int    $errno
     * @param string $errstr
     * @param string $errfile
     * @param int    $errline
     */
    public function handleError(int $errno, string $errstr, string $errfile, int $errline): void
    {
        $this->application->handleError($errno, $errstr, $errfile, $errline);
    }

    /**
     * Handle shutdown by delegating to the application's handler.
     */
    public function handleShutdown(): void
    {
        $this->application->handleShutdown();
    }
}
