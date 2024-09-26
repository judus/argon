<?php

namespace Maduser\Argon\Container;

use Exception;
use Maduser\Argon\App;

abstract class ServiceProvider
{
    public function __construct(protected ServiceContainer $container) {}

    /**
     * Resolve the service this container offers.
     *
     * @return mixed
     */
    abstract public function resolve(): mixed;

    /**
     * Register bindings, providers, configurations, commands, and routes.
     *
     * @throws Exception
     */
    public function register(): void
    {
        $this->registerBindings();
        $this->registerProviders();
        $this->registerConfig();
        $this->registerCommands();
        $this->registerRoutes();
    }

    /**
     * Register bindings (interface to class mappings).
     */
    protected function registerBindings(): void
    {
        $bindings = $this->bindings();
        if (!empty($bindings)) {
            $this->container->bind($bindings);
        }
    }

    /**
     * Register other service providers.
     *
     * @throws Exception
     */
    protected function registerProviders(): void
    {
        $providers = $this->providers();
        if (!empty($providers)) {
            $this->container->register($providers);
        }
    }

    /**
     * Register configuration.
     */
    protected function registerConfig(): void
    {
        $config = $this->config();
        if (!empty($config)) {
            //Config::push($config);
        }
    }

    /**
     * Register CLI commands.
     */
    protected function registerCommands(): void
    {
        $commands = $this->commands();
        if (!empty($commands)) {
            //Commands::register($commands);
        }
    }

    /**
     * Register routes.
     */
    protected function registerRoutes(): void
    {
        $this->routes();
    }

    /**
     * Provide configuration.
     *
     * @return array
     */
    public function config(): array
    {
        return [];
    }

    /**
     * Provide bindings (interface to class mappings).
     *
     * @return array
     */
    public function bindings(): array
    {
        return [];
    }

    /**
     * Provide other service providers.
     *
     * @return array
     */
    public function providers(): array
    {
        return [];
    }

    /**
     * Provide commands to register.
     *
     * @return array
     */
    public function commands(): array
    {
        return [];
    }

    /**
     * Register application routes.
     */
    public function routes(): void
    {
        // Optional routes method, can be overridden
    }

    /**
     * Return the container's class name.
     *
     * @return string
     */
    public function __toString(): string
    {
        return static::class;
    }
}
