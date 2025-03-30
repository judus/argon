<?php

declare(strict_types=1);

namespace Maduser\Argon\Container;

use Closure;
use Maduser\Argon\Container\Contracts\ServiceDescriptorInterface;
use Maduser\Argon\Container\Exceptions\ContainerException;

/**
 * Holds metadata about a service: its concrete implementation, singleton status,
 * optional constructor arguments, and optionally a factory.
 */
final class ServiceDescriptor implements ServiceDescriptorInterface
{
    private string $id;

    /**
     * @var class-string|Closure
     */
    private string|Closure $concrete;

    private bool $isSingleton;

    private ?object $instance = null;

    /**
     * @var array<string, mixed>
     */
    private array $arguments;

    /**
     * @var class-string|null
     */
    private ?string $factoryClass = null;

    /**
     * @var string|null
     */
    private ?string $factoryMethod = null;

    /**
     * @param string $id
     * @param class-string|Closure $concrete
     * @param bool $isSingleton
     * @param array<string, mixed> $arguments
     */
    public function __construct(string $id, string|Closure $concrete, bool $isSingleton, array $arguments = [])
    {
        $this->id = $id;
        $this->concrete = $concrete;
        $this->isSingleton = $isSingleton;
        $this->arguments = $arguments;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function isSingleton(): bool
    {
        return $this->isSingleton;
    }

    /**
     * @return class-string|Closure
     */
    public function getConcrete(): string|Closure
    {
        return $this->concrete;
    }

    public function getInstance(): ?object
    {
        return $this->instance;
    }

    public function storeInstance(object $instance): void
    {
        if ($this->isSingleton && $this->instance === null) {
            $this->instance = $instance;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function getArguments(): array
    {
        return $this->arguments;
    }

    /**
     * @param class-string $class
     * @param string|null $method
     * @throws ContainerException
     */
    public function setFactory(string $class, ?string $method = null): void
    {
        if (!class_exists($class)) {
            throw new ContainerException(
                sprintf('Factory class "%s" does not exist.', $class)
            );
        }

        if ($method === null) {
            $method = '__invoke';
        }

        if (!method_exists($class, $method)) {
            throw new ContainerException(
                sprintf('Factory method "%s" not found on class "%s".', $method, $class)
            );
        }

        $this->factoryClass = $class;
        $this->factoryMethod = $method;
    }

    public function hasFactory(): bool
    {
        return $this->factoryClass !== null;
    }

    public function getFactoryClass(): ?string
    {
        return $this->factoryClass;
    }

    public function getFactoryMethod(): string
    {
        return $this->factoryMethod ?? '__invoke';
    }
}
