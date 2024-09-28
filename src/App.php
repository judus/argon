<?php

declare(strict_types=1);

namespace Maduser\Argon;

use Closure;
use Exception;
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
class App extends Container
{
    private static ?Kernel $kernel = null;
    protected static ?ErrorHandler $errorHandler = null;
    protected static bool $booted = false;
    protected static array $contextStack = [];

    protected static array $kernelMap = [
        'cli' => CliApp::class,
        'cli-server' => CliApp::class,
        'apache2handler' => WebApp::class,
        'fpm-fcgi' => WebApp::class,
        'apache' => WebApp::class,
        'cgi-fcgi' => WebApp::class,
        'embed' => EmbeddedApp::class,
        'phpdbg' => DebugApp::class,
    ];

    /**
     * Initialize the application (Kernel) and set up the error handler.
     *
     * @param string|null $kernelClass The application class (default to environment-based)
     *
     * @return App Returns an instance of the App class to allow chaining.
     * @throws Exception
     */
    public static function init(?string $kernelClass = null): self
    {
        if (!isset(self::$kernel)) {
            self::$kernel = self::createKernel($kernelClass);

            $appErrorHandler = self::$kernel->getErrorHandler();
            self::setErrorHandler($appErrorHandler);

            if (!self::$booted) {
                self::$kernel->bootKernel();
                self::$booted = true;
            }
        }

        return new self();
    }

    /**
     * Get the default kernel class based on the server environment.
     *
     * @return string The kernel class name
     * @throws Exception If an unsupported environment is detected
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
     * Create the Kernel.
     *
     * @param string|null $kernelClass Optional kernel class.
     *
     * @return Kernel The created kernel instance
     * @throws Exception
     */
    protected static function createKernel(?string $kernelClass = null): Kernel
    {
        $kernelClass = $kernelClass ?? self::getDefaultKernelClass();

        /**
 * @psalm-suppress LessSpecificReturnStatement
*/
        return new $kernelClass(self::getProvider());
    }

    /**
     * Sets the ErrorHandler by resolving it via the container or using a given instance.
     *
     * @param string|ErrorHandler $errorHandler The error handler class name or instance
     *
     * @throws Exception
     */
    public static function setErrorHandler(string|ErrorHandler $errorHandler): void
    {
        if (is_string($errorHandler)) {
            $errorHandler = self::$provider->make($errorHandler);
        }

        if ($errorHandler instanceof ErrorHandler) {
            self::$errorHandler = $errorHandler;
        } else {
            throw new Exception("ErrorHandler must be an instance of ErrorHandler or a class name.");
        }
    }

    /**
     * Gets the registered ErrorHandler, if any.
     *
     * @return ErrorHandler|null
     */
    public static function getErrorHandler(): ?ErrorHandler
    {
        return self::$errorHandler;
    }

    /**
     * Run the application. Non-static method to allow chaining after init().
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

        self::getKernel()->handle(null);
    }

    /**
     * Dispatches a callback in an isolated service container context.
     *
     * @param Closure     $callback    The callback to execute
     * @param string|null $kernelClass
     *
     * @throws Exception
     */
    public static function dispatch(Closure $callback, ?string $kernelClass = null): void
    {
        self::startContext();

        $kernel = self::createKernel($kernelClass);
        $kernel->bootKernel();
        $kernel->handle($callback);

        self::endContext();
    }

    /**
     * Gets the current kernel instance, initializing it if necessary.
     *
     * @return Kernel The current kernel instance
     * @throws Exception If initialization fails
     */
    public static function getKernel(): Kernel
    {
        if (!isset(self::$kernel)) {
            self::$kernel = self::createKernel();
        }
        return self::$kernel;
    }

    /**
     * Starts a new container context.
     */
    protected static function startContext(): void
    {
        // Push the current container onto the stack
        self::$contextStack[] = self::getProvider();

        // Create a new container for the new context
        self::$provider = new ServiceContainer();
    }

    /**
     * Ends the current container context.
     */
    protected static function endContext(): void
    {
        // Pop the context stack to restore the previous container
        if (!empty(self::$contextStack)) {
            self::$provider = array_pop(self::$contextStack);
        }
    }
}
