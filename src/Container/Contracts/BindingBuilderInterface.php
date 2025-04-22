<?php

declare(strict_types=1);

namespace Maduser\Argon\Container\Contracts;

/**
 * Fluent interface for configuring service bindings.
 *
 * Returned by container binding methods (e.g., `set()`) to allow chaining
 * configuration such as factories, method invocation definitions, tags,
 * lifecycle controls, and compilation behavior.
 */
interface BindingBuilderInterface
{
    /**
     * Assigns a factory class and optional method for service instantiation.
     *
     * If no method is specified, `__invoke()` is assumed.
     *
     * @param class-string $factoryClass The class used to build the service
     * @param string|null $method Optional method on the factory class
     * @return BindingBuilderInterface Fluent chain
     */
    public function factory(string $factoryClass, ?string $method = null): BindingBuilderInterface;

    /**
     * Defines method invocation metadata for the compiler.
     *
     * This maps method names to argument types, enabling invoke() to work
     * without reflection at runtime.
     *
     * @param string $methodName Method to define
     * @param array<array-key, class-string|string|int|float|bool|null> $args Arguments to inject
     * @return BindingBuilderInterface Fluent chain
     *
     * @api
     */
    public function defineInvocation(string $methodName, array $args = []): BindingBuilderInterface;

    /**
     * Tags the service with one or more labels for grouped access.
     *
     * Accepts a string or a list of strings. Services with tags can be resolved
     * later using `$container->getTagged($tag)`.
     *
     * @param string|array<int|string, string|array<string, mixed>> $tags
     * @return BindingBuilderInterface Fluent chain
     */
    public function tag(array|string $tags): BindingBuilderInterface;

    /**
     * Prevents this service from being included in the compiled container.
     *
     * Useful for runtime-only closures or dynamic service bindings that
     * are not intended to be part of the static output.
     *
     * @return BindingBuilderInterface Fluent chain
     *
     * @api
     */
    public function skipCompilation(): BindingBuilderInterface;

    /**
     * Marks the service as transient (non-shared).
     *
     * A transient service will be instantiated on every call to get(),
     * even if the same binding is reused. This overrides the default shared lifecycle.
     *
     * @return BindingBuilderInterface Fluent chain
     *
     * @api
     */
    public function transient(): BindingBuilderInterface;

    /**
     * Returns the underlying service descriptor for introspection or extension.
     *
     * This can be used to manipulate low-level descriptor metadata, though this
     * is rarely needed in typical container usage.
     */
    public function getDescriptor(): ServiceDescriptorInterface;
}
