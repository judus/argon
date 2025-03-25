<?php

declare(strict_types=1);

namespace Maduser\Argon\Container\Contracts;

use Closure;

/**
 * Holds metadata about a service: its concrete implementation and singleton state.
 */
interface ServiceDescriptorInterface
{
    public function isSingleton(): bool;

    /**
     * @return class-string|Closure
     */
    public function getConcrete(): string|Closure;

    public function getInstance(): ?object;

    public function storeInstance(object $instance): void;
}