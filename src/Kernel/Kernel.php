<?php

declare(strict_types=1);

namespace Maduser\Argon\Kernel;

use Maduser\Argon\Container\ServiceContainer;
use Throwable;

abstract class Kernel
{
    protected string $errorHandler = ErrorHandler::class;

    protected ServiceContainer $provider;
    private bool $booted = false;

    public function __construct(ServiceContainer $provider)
    {
        $this->provider = $provider;
    }

    /**
     * Set up the application (register services, bindings, etc.)
     */
    /**
     * Public method to handle booting.
     * Ensures boot() is called only once.
     */
    public function bootKernel(): void
    {
        if ($this->booted) return;

        $this->boot();

        $this->booted = true;
    }

    /**
     * Abstract method for user-defined boot logic.
     * This will be implemented by the user's custom kernel.
     */
    protected function boot(): void
    {
        // User-defined boot logic
    }

    /**
     * Returns the error handler instance, either a default one or a custom one.
     *
     * @return ErrorHandler
     */
    public function getErrorHandler(): ErrorHandler
    {
        return new $this->errorHandler($this);
    }

    /**
     * Execute the callback (or perform other tasks), with exception handling.
     *
     * @param callable|null $callback The callback to execute (optional)
     */
    public function handle(?callable $callback = null): void
    {
        $callback && $callback();
    }

    /**
     * Default method to handle exceptions.
     *
     * @param Throwable $exception
     */
    public function handleException(Throwable $exception): void
    {
        // Default exception handling logic
        echo "An exception occurred: " . $exception->getMessage() . PHP_EOL;

        // Log the exception details as a string
        error_log(sprintf(
            "Exception [%s]: %s in %s on line %d\nStack trace:\n%s",
            get_class($exception),
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine(),
            $exception->getTraceAsString()
        ));
    }

    /**
     * Default method to handle errors.
     *
     * @param int    $errno
     * @param string $errstr
     * @param string $errfile
     * @param int    $errline
     */
    public function handleError(int $errno, string $errstr, string $errfile, int $errline): void
    {
        // Default error handling logic
        echo "An error occurred: [$errno] $errstr in $errfile on line $errline" . PHP_EOL;
        error_log("Error [$errno]: $errstr in $errfile on line $errline");
    }

    /**
     * Default method to handle shutdown (for fatal errors).
     */
    public function handleShutdown(): void
    {
        $error = error_get_last();
        if ($error !== null) {
            $this->handleError($error['type'], $error['message'], $error['file'], $error['line']);
        }
    }
}