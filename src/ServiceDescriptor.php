<?php

declare(strict_types=1);

namespace Maduser\Argon\Container;

use Closure;
use Maduser\Argon\Container\Contracts\ServiceDescriptorInterface;
use Maduser\Argon\Container\Exceptions\ContainerException;
use Override;

/**
 * @inheritDoc
 */
final class ServiceDescriptor implements ServiceDescriptorInterface
{
    private string $id;

    /**
     * @var class-string|Closure
     */
    private string|Closure $concrete;

    private bool $isShared;

    private ?object $instance = null;

    /**
     * @var array<array-key, mixed>
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

    private bool $shouldCompile = true;

    /**
     * @var array<string, array<array-key, class-string|string|int|float|bool|null>>
     */
    private array $invocationMap = [];

    /**
     * @param class-string|Closure $concrete
     */
    public function __construct(string $id, string|Closure $concrete, bool $isShared, ?array $arguments = [])
    {
        $this->id = $id;
        $this->concrete = $concrete;
        $this->isShared = $isShared;
        $this->arguments = $arguments ?? [];
    }

    /** @inheritDoc */
    #[Override]
    public function getId(): string
    {
        return $this->id;
    }

    /** @inheritDoc */
    #[Override]
    public function isShared(): bool
    {
        return $this->isShared;
    }

    /** @inheritDoc */
    #[Override]
    public function setShared(bool $isShared): void
    {
        $this->isShared = $isShared;
    }

    /** @inheritDoc */
    #[Override]
    public function getConcrete(): string|Closure
    {
        return $this->concrete;
    }

    /** @inheritDoc */
    #[Override]
    public function getArguments(): array
    {
        return $this->arguments;
    }

    /** @inheritDoc */
    #[Override]
    public function hasArgument(string $name): bool
    {
        return array_key_exists($name, $this->arguments);
    }

    /** @inheritDoc */
    #[Override]
    public function setArgument(string $name, mixed $value): void
    {
        $this->arguments[$name] = $value;
    }

    /** @inheritDoc */
    #[Override]
    public function getArgument(string $name): mixed
    {
        if (!array_key_exists($name, $this->arguments)) {
            throw new ContainerException("Constructor argument [$name] not found in service [$this->id]");
        }

        return $this->arguments[$name];
    }

    /** @inheritDoc
     * @throws ContainerException
     */
    #[Override]
    public function setFactory(string $class, ?string $method = null): void
    {
        if (!class_exists($class) && !interface_exists($class)) {
            throw new ContainerException(
                sprintf('Factory class or interface "%s" does not exist.', $class)
            );
        }

        $this->factoryClass = $class;
        $this->factoryMethod = $method ?? '__invoke';
    }

    /** @inheritDoc */
    #[Override]
    public function hasFactory(): bool
    {
        return $this->factoryClass !== null;
    }

    /** @inheritDoc */
    #[Override]
    public function getFactoryClass(): ?string
    {
        return $this->factoryClass;
    }

    /** @inheritDoc */
    #[Override]
    public function getFactoryMethod(): string
    {
        return $this->factoryMethod ?? '__invoke';
    }

    /** @inheritDoc */
    #[Override]
    public function defineInvocation(string $method, array $arguments, ?string $returnType = null): void
    {
        $this->invocationMap[$method] = $arguments;
    }

    /** @inheritDoc */
    #[Override]
    public function getInvocation(string $method): array
    {
        return $this->invocationMap[$method] ?? [];
    }

    /** @inheritDoc */
    #[Override]
    public function getInvocationMap(): array
    {
        return $this->invocationMap;
    }

    /** @inheritDoc */
    #[Override]
    public function getInstance(): ?object
    {
        return $this->instance;
    }

    /** @inheritDoc */
    #[Override]
    public function storeInstance(object $instance): void
    {
        if ($this->isShared && $this->instance === null) {
            $this->instance = $instance;
        }
    }

    /** @inheritDoc */
    #[Override]
    public function skipCompilation(): self
    {
        $this->shouldCompile = false;
        return $this;
    }

    /** @inheritDoc */
    #[Override]
    public function shouldCompile(): bool
    {
        return $this->shouldCompile;
    }
}
