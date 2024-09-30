<?php

namespace Maduser\Argon\Container;

abstract class ServiceProvider
{
    protected ServiceContainer $container;

    public function __construct(ServiceContainer $container)
    {
        $this->container = $container;
    }

    public function register(): void
    {
    }

    /**
     * Resolve the service this container offers.
     *
     * @return mixed
     */
    public function resolve(): mixed
    {
        return null;
    }

//    /**
//     * Register bindings, providers, configurations, commands, and routes.
//     *
//     * @throws Exception
//     */
//    public function register(): void
//    {
//        $this->registerBindings();
//        $this->registerProviders();
//        $this->registerConfig();
//        $this->registerCommands();
//        $this->registerRoutes();
//    }
//
//    /**
//     * Register bindings (interface to class mappings).
//     */
//    protected function registerBindings(): void
//    {
//        $bindings = $this->bindings();
//        if (!empty($bindings)) {
//            $this->container->bind($bindings);
//        }
//    }
//
//    /**
//     * Provide bindings (interface to class mappings).
//     *
//     * @return array
//     *
//     * @psalm-return array<string, class-string>
//     */
//    public function bindings(): array
//    {
//        return [];
//    }
//
//    /**
//     * Register other service providers.
//     *
//     * @throws Exception
//     */
//    protected function registerProviders(): void
//    {
//        $providers = $this->providers();
//        if (!empty($providers)) {
//            $this->container->set($providers);
//        }
//    }
//
//    /**
//     * Provide other service providers.
//     *
//     * @return array
//     *
//     * @psalm-return array<string, class-string>
//     */
//    public function providers(): array
//    {
//        return [];
//    }
//
//    /**
//     * Register configuration.
//     */
//    protected function registerConfig(): void
//    {
//        $config = $this->config();
//        if (!empty($config)) {
//            //Config::push($config);
//        }
//    }
//
//    /**
//     * Provide configuration.
//     *
//     * @return array
//     *
//     * @psalm-return array<string, mixed>
//     */
//    public function config(): array
//    {
//        return [];
//    }
//
//    /**
//     * Register CLI commands.
//     */
//    protected function registerCommands(): void
//    {
//        $commands = $this->commands();
//        if (!empty($commands)) {
//            //Commands::register($commands);
//        }
//    }
//
//    /**
//     * Provide commands to register.
//     *
//     * @return array<string, class-string>
//     */
//    public function commands(): array
//    {
//        return [];
//    }
//
//    /**
//     * Register routes.
//     */
//    protected function registerRoutes(): void
//    {
//        $this->routes();
//    }
//
//    /**
//     * Register application routes.
//     */
//    public function routes(): void
//    {
//        // Optional routes method, can be overridden
//    }

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
