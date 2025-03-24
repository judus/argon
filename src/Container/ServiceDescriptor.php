<?php

declare(strict_types=1);

namespace Maduser\Argon\Container;

use Closure;

/**
 * Class ServiceDescriptor
 *
 * Holds metadata about a service, including whether it's a singleton and the concrete implementation.
 */
class ServiceDescriptor
{
    /**
     * @var class-string|Closure
     */
    private string|Closure $concrete;
    private bool $isSingleton;
    private ?object $instance = null;

    /**
     * @param class-string|Closure $concrete
     */
    public function __construct(string|Closure $concrete, bool $isSingleton)
    {
        $this->concrete = $concrete;
        $this->isSingleton = $isSingleton;
    }

    /**
     * Checks if the service is a singleton.
     *
     * @return bool
     */
    public function isSingleton(): bool
    {
        return $this->isSingleton;
    }

    /**
     * Returns the concrete class or closure that defines the service.
     *
     * @return string|Closure
     * @psalm-return class-string|Closure
     */
    public function getConcrete(): string|Closure
    {
        return $this->concrete;
    }

    /**
     * Returns the singleton instance if available.
     *
     * @return object|null
     */
    public function getInstance(): ?object
    {
        return $this->instance;
    }

    /**
     * Stores the resolved instance for singletons.
     *
     * @param object $instance
     * @return void
     */
    public function storeInstance(object $instance): void
    {
        if ($this->isSingleton && $this->instance === null) {
            $this->instance = $instance;
        }
    }
}
