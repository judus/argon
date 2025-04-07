<?php

declare(strict_types=1);

namespace Maduser\Argon\Container\Contracts;

use Closure;

/**
 * Holds metadata about a service: its concrete implementation and singleton state.
 */
interface ServiceDescriptorInterface
{
    public function getId(): string;

    public function isSingleton(): bool;

    /**
     * @return class-string|Closure
     */
    public function getConcrete(): string|Closure;

    public function getInstance(): ?object;
    public function getArguments(): array;

    public function hasArgument(string $name): bool;

    public function setArgument(string $name, mixed $value): void;

    public function getArgument(string $name): mixed;

    /**
     * @return array<array-key, array<array-key, class-string|string|int|float|bool|null>>
     */
    public function getMethodMap(): array;

    public function storeInstance(object $instance): void;

    /**
     * @param class-string $class
     */
    public function setFactory(string $class, ?string $method = null): void;

    public function hasFactory(): bool;

    /**
     * @return class-string|null
     */
    public function getFactoryClass(): ?string;

    public function getFactoryMethod(): string;

    /**
     * @param string $method
     * @param array<array-key, class-string|string|int|float|bool|null> $arguments
     * @param string|null $returnType
     * @return void
     */
    public function setMethod(string $method, array $arguments, ?string $returnType = null): void;

    public function getMethod(string $method): array;

    /**
     * @return array<array-key, array<array-key, class-string|string|int|float|bool|null>>
     */
    public function getAllMethods(): array;

    /**
     * @api
     */
    public function compilerIgnore(): self;

    public function shouldIgnoreForCompilation(): bool;
}
