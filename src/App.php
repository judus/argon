<?php

declare(strict_types=1);

namespace Maduser\Argon;

use Closure;
use Exception;
use Maduser\Argon\Container\ContainerFacade;
use Maduser\Argon\Container\ServiceContainer;
use Maduser\Argon\Kernel\EnvApp\CliApp;
use Maduser\Argon\Kernel\EnvApp\DebugApp;
use Maduser\Argon\Kernel\EnvApp\EmbeddedApp;
use Maduser\Argon\Kernel\EnvApp\WebApp;
use Maduser\Argon\Kernel\ErrorHandler;
use Maduser\Argon\Kernel\Kernel;

/**
 * @psalm-api App
 */
class App extends ContainerFacade
{
    protected static ?ErrorHandler $errorHandler = null;
    protected static bool $booted = false;
    protected static array $contextStack = [];
    protected static array $kernelMap = [
        'cli' => CliApp::class,
        'cli-server' => CliApp::class,
        'apache2handler' => WebApp::class,
        'fpm-fcgi' => WebApp::class,
        'phpdbg' => DebugApp::class,
        'embed' => EmbeddedApp::class,
    ];
    private static ?Kernel $kernel = null;

    /**
     * Initialize the application kernel and set up the error handler.
     *
     * @param string|null $kernelClass
     * @return App
     * @throws Exception
     */
    public static function init(?string $kernelClass = null): self
    {
        if (!isset(self::$kernel)) {
            self::$kernel = self::createKernel($kernelClass);

            // Set up the error handler
            self::setErrorHandler(self::$kernel->getErrorHandler());

            if (!self::$booted) {
                self::$kernel->bootKernel();
                self::$booted = true;
            }
        }

        return new self();
    }

    /**
     * Creates and returns a kernel based on the environment or provided class.
     *
     * @param string|null $kernelClass
     * @return Kernel
     * @throws Exception
     */
    protected static function createKernel(?string $kernelClass = null): Kernel
    {
        $kernelClass = $kernelClass ?? self::getDefaultKernelClass();
        return new $kernelClass(self::container());
    }

    /**
     * Determines the kernel class to use based on the server environment.
     *
     * @return string
     * @throws Exception
     */
    public static function getDefaultKernelClass(): string
    {
        $sapi = php_sapi_name();

        if (isset(self::$kernelMap[$sapi])) {
            return self::$kernelMap[$sapi];
        }

        throw new Exception("Unsupported environment: $sapi");
    }

    /**
     * Set the error handler either by class name or instance.
     *
     * @param string|ErrorHandler $errorHandler
     * @throws Exception
     */
    public static function setErrorHandler(string|ErrorHandler $errorHandler): void
    {
        if (is_string($errorHandler)) {
            $errorHandler = self::$container->get($errorHandler);
        }

        if ($errorHandler instanceof ErrorHandler) {
            self::$errorHandler = $errorHandler;
        } else {
            throw new Exception("ErrorHandler must be an instance of ErrorHandler or its class name.");
        }
    }

    /**
     * Dispatches a callback in an isolated service container context.
     *
     * @param Closure     $callback
     * @param string|null $kernelClass
     * @throws Exception
     */
    public static function dispatch(Closure $callback, ?string $kernelClass = null): void
    {
        self::startContext();

        // Create and boot a new kernel
        $kernel = self::createKernel($kernelClass);
        $kernel->bootKernel();
        $kernel->handle($callback);

        self::endContext();
    }

    /**
     * Starts a new container context.
     * @throws Exception
     */
    protected static function startContext(): void
    {
        // Save the current container state
        self::$contextStack[] = self::container();

        // Create a fresh container for this context
        self::setContainer(new ServiceContainer());
    }

    /**
     * Ends the current container context and restores the previous one.
     */
    protected static function endContext(): void
    {
        // Restore the previous container from the stack
        if (!empty(self::$contextStack)) {
            self::setContainer(array_pop(self::$contextStack));
        }
    }

    /**
     * Run the application.
     * @throws Exception
     */
    public function run(): void
    {
        self::launch();
    }

    /**
     * Launch the application (static).
     *
     * @throws Exception
     */
    public static function launch(): void
    {
        if (!self::$booted) {
            self::getKernel()->bootKernel();
            self::$booted = true;
        }

        self::getKernel()->handle();
    }

    /**
     * Gets the current kernel instance, initializing if necessary.
     *
     * @return Kernel
     * @throws Exception
     */
    public static function getKernel(): Kernel
    {
        if (!isset(self::$kernel)) {
            self::$kernel = self::createKernel();
        }

        return self::$kernel;
    }
}
