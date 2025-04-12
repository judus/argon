<?php

declare(strict_types=1);

namespace Maduser\Argon\Container\Contracts;

use Closure;
use Maduser\Argon\Container\Exceptions\ContainerException;

/**
 * Defines the internal service metadata used by the container.
 *
 * A service descriptor stores construction metadata including concrete class,
 * lifecycle (shared or transient), constructor arguments, factory logic, and
 * optionally, precompiled invocation definitions to support zero-reflection method calls.
 */
interface ServiceDescriptorInterface
{
    /**
     * Returns the service ID (usually the fully-qualified class name or alias).
     */
    public function getId(): string;

    /**
     * Whether the service uses a singleton lifecycle (shared instance).
     *
     * If true, the container will reuse the same instance across all calls to get().
     */
    public function isShared(): bool;

    /**
     * Sets whether the service uses a shared lifecycle.
     *
     * When true, the container will reuse a single instance across calls to get().
     * When false, the container will create a new instance each time (transient lifecycle).
     *
     * This is typically set internally via fluent builder configuration.
     *
     * @param bool $isShared True for shared (singleton-style), false for transient
     */
    public function setShared(bool $isShared): void;

    /**
     * Returns the concrete class or closure used to instantiate the service.
     *
     * @return class-string|Closure
     */
    public function getConcrete(): string|Closure;

    /**
     * Returns all explicitly defined constructor arguments.
     *
     * @return array<array-key, mixed>
     */
    public function getArguments(): array;

    /**
     * Checks whether a constructor argument has been explicitly defined.
     */
    public function hasArgument(string $name): bool;

    /**
     * Sets or overrides a constructor argument.
     */
    public function setArgument(string $name, mixed $value): void;

    /**
     * Retrieves a constructor argument by name.
     *
     * @throws ContainerException
     */
    public function getArgument(string $name): mixed;

    /**
     * Defines a factory class and method to use for instantiating this service.
     *
     * The method must be resolvable from the container. Defaults to __invoke().
     *
     * @param class-string $class
     * @param string|null $method
     */
    public function setFactory(string $class, ?string $method = null): void;

    /**
     * Returns whether a factory has been defined for this service.
     */
    public function hasFactory(): bool;

    /**
     * Returns the factory class if defined.
     *
     * @return class-string|null
     */
    public function getFactoryClass(): ?string;

    /**
     * Returns the method to call on the factory class.
     *
     * Defaults to "__invoke" if no specific method was defined.
     */
    public function getFactoryMethod(): string;

    /**
     * Defines argument resolution metadata for a specific method.
     *
     * This allows the container's invoke() logic to execute a method
     * without using runtime reflection.
     *
     * @param string $method Name of the method being precompiled
     * @param array<array-key, class-string|string|int|float|bool|null> $arguments Argument definitions for the method
     * @param string|null $returnType Optional return type hint (currently unused)
     */
    public function defineInvocation(string $method, array $arguments, ?string $returnType = null): void;

    /**
     * Returns argument definitions for a single invocable method.
     *
     * Used by the container's invoke() method to match parameters.
     *
     * @param string $method
     * @return array<array-key, class-string|string|int|float|bool|null>
     */
    public function getInvocation(string $method): array;

    /**
     * Returns the complete map of precompiled invocations.
     *
     * The map is keyed by method name and contains argument definitions.
     *
     * @return array<string, array<array-key, class-string|string|int|float|bool|null>>
     */
    public function getInvocationMap(): array;

    /**
     * Returns the cached instance, if this service is a singleton and has been instantiated.
     */
    public function getInstance(): ?object;

    /**
     * Stores the resolved instance if the service uses a shared lifecycle.
     */
    public function storeInstance(object $instance): void;

    /**
     * Marks this service as not eligible for container compilation.
     *
     * Used for runtime-only services (e.g., closures, env-specific bindings).
     *
     * @api
     */
    public function skipCompilation(): self;

    /**
     * Returns whether this service should be included in the compilation output.
     */
    public function shouldCompile(): bool;
}
