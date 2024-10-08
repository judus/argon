<?php

namespace Maduser\Argon\Kernel\EnvApp;

use Exception;
use Maduser\Argon\Kernel\Kernel;
use Maduser\Console\CommandManager;
use Maduser\Console\Console;
use ReflectionException;

class CliApp extends Kernel
{
    private ?object $console = null;
    private ?object $commandManager = null;
    private ?object $pipeline = null;

    /**
     * Boot method to register default CLI services dynamically.
     *
     * @throws Exception
     */
    public function boot(): void
    {
        if (class_exists('\Maduser\Console\Console')) {
            $this->container->bind('console', function () {
                return new Console();  // Inject container manually if needed
            });
            $this->console = $this->container->get('console');
        }

        if (class_exists('Maduser\Console\CommandManager')) {
            $this->container->singleton('Maduser\Console\CommandManager', function () {
                return new CommandManager($this->container);  // Inject container manually
            });
            $this->commandManager = $this->container->get('Maduser\Console\CommandManager');
        }
    }

    /**
     * Handle CLI execution with optional services.
     *
     * @param callable|null $callback Optional callback to execute during handling.
     */
    public function handle(?callable $callback = null): void
    {
        if ($this->pipeline) {
            $this->pipeline->process($this, function () use ($callback) {
                $this->operation($callback);
            });
        } else {
            $this->operation($callback);
        }
    }

    public function operation(?callable $callback = null): void
    {
        global $argv;

        $commandName = $argv[1] ?? null;
        $arguments = array_slice($argv, 2);
        // Execute the callback, passing the console if available
        $this->executeCallback($callback, $arguments);
        // If CommandManager is available, process the command

        if ($this->commandManager) {
            $this->dispatchCommand($commandName, $arguments);
        }
    }

    /**
     * Executes the callback with available arguments.
     *
     * @param callable|null $callback
     * @param array         $arguments
     *
     * @throws ReflectionException
     */
    private function executeCallback(?callable $callback, array $arguments): void
    {
        if (!is_null($callback)) {
            // Inject the current service container into the arguments
            array_unshift($arguments, $this->container);

            // Call the callback with the arguments, including the provider
            $this->container->call($callback, null, ['container' => $this->container]);
        }
    }

    /**
     * Dispatches a command if a command name is provided.
     *
     * @param string|null $commandName
     * @param array       $arguments
     */
    private function dispatchCommand(?string $commandName, array $arguments): void
    {

        global $argv;

        // Skip dispatching if running PHPUnit
        if (is_null($commandName) && (defined('PHPUNIT_COMPOSER_INSTALL') || defined('__PHPUNIT_PHAR__'))) {
            return;
        }
        if (is_null($commandName) && !is_null($this->console)) {
            $this->listAvailableCommands();

            return;
        }


        if (is_string($commandName)) {
            $this->commandManager->dispatch($commandName, $arguments);
        }
    }

    /**
     * Lists available commands if no specific command is provided.
     */
    private function listAvailableCommands(): void
    {
        $this->console->br()->bold("Available commands:");
        $commands = $this->commandManager->listCommands();
        $this->printCommandList($commands);
    }

    /**
     * Outputs a formatted list of available commands and their descriptions.
     *
     * @param array $commands
     */
    private function printCommandList(array $commands): void
    {
        // Sort the commands by name (array key) in alphabetical order
        ksort($commands);

        foreach ($commands as $name => $description) {
            $this->console->info(sprintf(" - %-30s %s", $name, $description ?? ''));
        }

        $this->console->br();
    }
}
