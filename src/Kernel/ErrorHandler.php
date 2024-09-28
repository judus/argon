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

        /** @psalm-suppress InvalidArgument */
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
     * Handle PHP errors.
     *
     * @param int    $errno     The level of the error raised
     * @param string $errstr    The error message
     * @param string $errfile   The filename that the error was raised in
     * @param int    $errline   The line number the error was raised at
     * @param array $errcontext An array that points to the active symbol table at the
     *                          point the error occurred (optional, depending on PHP version)
     *
     * @return bool|null
     */
    public function handleError(
        int $errno,
        string $errstr,
        string $errfile,
        int $errline,
        array $errcontext = []
    ): ?bool {
        // Handle the error and return true to indicate that the PHP internal error handler should not handle it.
        return $this->application->handleError($errno, $errstr, $errfile, $errline, $errcontext);
    }

    /**
     * Handle shutdown by delegating to the application's handler.
     */
    public function handleShutdown(): void
    {
        $this->application->handleShutdown();
    }
}
