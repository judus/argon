<?php

namespace Maduser\Argon\Container;

namespace Maduser\Argon\Container;

trait Hookable
{
    /**
     * @var array<string, callable> Post-resolution hooks
     */
    protected array $postResolutionHooks = [];

    /**
     * @var array<string, callable> Setter hooks
     */
    private array $setterHooks = [];

    /**
     * @var array<string, callable> Pre-resolution hooks
     */
    private array $preResolutionHooks = [];

    /**
     * Adds a setter hook for a specific type.
     *
     * @param string   $type    The type or interface to hook into.
     * @param callable $handler The handler to invoke.
     */
    public function addSetterHook(string $type, callable $handler): void
    {
        $this->setterHooks[$type] = $handler;
    }

    /**
     * Adds a pre-resolution hook.
     *
     * @param string   $type    The class or interface to hook into
     * @param callable $handler The handler to invoke for this type
     */
    public function addPreResolutionHook(string $type, callable $handler): void
    {
        $this->preResolutionHooks[$type] = $handler;
    }

    /**
     * Adds a post-resolution hook.
     *
     * @param string   $type    The class or interface to hook into
     * @param callable $handler The handler to invoke for this type
     */
    public function addPostResolutionHook(string $type, callable $handler): void
    {
        $this->postResolutionHooks[$type] = $handler;
    }

    /**
     * Executes pre-resolution hooks for a specific type.
     *
     * @param ServiceDescriptor $descriptor
     * @param array|null        $params
     *
     * @return mixed|null
     */
    public function handlePreResolutionHooks(ServiceDescriptor $descriptor, ?array $params = []): mixed
    {
        return $this->executeHooks($this->preResolutionHooks, $descriptor->getClassName(), [$descriptor, $params]);
    }

    /**
     * Executes hooks for a specific type or class name.
     *
     * @param array  $hooks     The array of hooks
     * @param string $className The class or interface to check
     * @param array  $args      Arguments to pass to the hook handler
     *
     * @return mixed|null The result of the hook or null if no hook was executed
     */
    private function executeHooks(array $hooks, string $className, array $args): mixed
    {
        foreach ($hooks as $type => $handler) {
            if (is_subclass_of($className, $type) || $className === $type) {
                return call_user_func_array($handler, $args);
            }
        }

        return null;
    }

    /**
     * Executes post-resolution hooks for a specific type.
     *
     * @param object                 $instance
     * @param ServiceDescriptor|null $descriptor
     *
     * @return mixed
     */
    public function handlePostResolutionHooks(object $instance, ?ServiceDescriptor $descriptor = null): mixed
    {
        return $this->executeHooks($this->postResolutionHooks, get_class($instance),
            [$instance, $descriptor]) ?? $instance;
    }

    /**
     * Handles setter hooks when a service is registered.
     *
     * @param ServiceDescriptor $descriptor
     * @param string            $alias
     */
    private function handleSetterHooks(ServiceDescriptor $descriptor, string $alias): void
    {
        $this->executeHooks($this->setterHooks, $descriptor->getClassName(), [$descriptor, $alias]);
    }
}
