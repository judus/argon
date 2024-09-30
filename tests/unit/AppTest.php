<?php

declare(strict_types=1);

namespace Maduser\Argon\Tests\Unit;

use Maduser\Argon\App;
use Maduser\Argon\Container\ServiceContainer;
use Maduser\Argon\Kernel\EnvApp\CliApp;
use Maduser\Argon\Kernel\ErrorHandler;
use Maduser\Argon\Kernel\Kernel;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class AppTest extends TestCase
{
    public function setUp(): void
    {
        // Reset the static properties before each test to avoid state leakage
        $this->resetAppState();
    }

    protected function resetAppState(): void
    {
        $refApp = new ReflectionClass(App::class);
        $kernel = $refApp->getProperty('kernel');
        $kernel->setAccessible(true);
        $kernel->setValue(null);

        $booted = $refApp->getProperty('booted');
        $booted->setAccessible(true);
        $booted->setValue(false);

        $contextStack = $refApp->getProperty('contextStack');
        $contextStack->setAccessible(true);
        $contextStack->setValue([]);
    }

    public function testAppInitialization(): void
    {
        // Initialize the app with a default kernel (e.g., CLI kernel)
        $app = App::init(CliApp::class);
        $this->assertInstanceOf(App::class, $app);

        // Ensure the kernel was initialized properly
        $kernel = $this->getKernelInstance();
        $this->assertInstanceOf(CliApp::class, $kernel);
    }

    protected function getKernelInstance(): ?Kernel
    {
        $refApp = new ReflectionClass(App::class);
        $kernel = $refApp->getProperty('kernel');
        $kernel->setAccessible(true);

        return $kernel->getValue(null);
    }

    public function testDefaultKernelIsSet(): void
    {
        // Ensure the default kernel is correctly set during initialization
        App::init();
        $kernel = App::getKernel();

        // Assert that kernel is an instance of Kernel
        $this->assertInstanceOf(Kernel::class, $kernel);
    }

    public function testKernelBooting(): void
    {
        // Initialize the app and ensure the kernel boots correctly
        App::init(CliApp::class);

        // Verify the kernel is booted
        $this->assertTrue($this->isKernelBooted());
    }

    protected function isKernelBooted(): bool
    {
        $refApp = new ReflectionClass(App::class);
        $booted = $refApp->getProperty('booted');
        $booted->setAccessible(true);

        return $booted->getValue(null);
    }

    public function testContextStack(): void
    {
        // Initialize the app with CliApp
        App::init(CliApp::class);

        // Save the original container instance
        $originalProvider = App::container();

        // Start a new context using the dispatch method
        App::dispatch(function () use ($originalProvider) {
            // After starting a new context, the container should be different
            $newProvider = App::container();

            // Assert that the new container is not the same as the original
            $this->assertNotSame($originalProvider, $newProvider);
        });

        // Ensure the original container is restored after dispatch
        $restoredProvider = App::container();
        $this->assertSame($originalProvider, $restoredProvider);
    }

    public function testSetErrorHandler(): void
    {
        App::init(CliApp::class);

        $customHandler = $this->createMock(ErrorHandler::class);
        App::setErrorHandler($customHandler);

        // Ensure the custom error handler was set
        $this->assertSame($customHandler, App::getErrorHandler());
    }

    protected function getOriginalProvider(): ServiceContainer
    {
        $refApp = new ReflectionClass(App::class);
        $provider = $refApp->getProperty('container');
        $provider->setAccessible(true);

        return $provider->getValue(null);
    }
}
