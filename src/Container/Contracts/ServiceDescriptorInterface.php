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

    public function setMethod(string $method, array $arguments, ?string $returnType = null): void;

    public function getMethod(string $method): array;

    /**
     * @api
     */
    public function compilerIgnore(): self;

    public function shouldIgnoreForCompilation(): bool;
}
