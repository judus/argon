<?php

declare(strict_types=1);

namespace Maduser\Argon\Container;

use Maduser\Argon\Container\Contracts\BindingBuilderInterface;
use Maduser\Argon\Container\Contracts\ServiceDescriptorInterface;
use Maduser\Argon\Container\Contracts\TagManagerInterface;
use Maduser\Argon\Container\Exceptions\ContainerException;

final readonly class BindingBuilder implements BindingBuilderInterface
{
    public function __construct(
        private ServiceDescriptorInterface $descriptor,
        private readonly TagManagerInterface $tagManager,
    ) {
    }

    public function getDescriptor(): ServiceDescriptorInterface
    {
        return $this->descriptor;
    }

    public function setMethod(string $methodName, array $args = []): BindingBuilderInterface
    {
        $this->descriptor->setMethod($methodName, $args);

        return $this;
    }

    /**
     * @param class-string $factoryClass
     * @param string|null $method
     * @return BindingBuilderInterface
     */
    public function useFactory(string $factoryClass, ?string $method = null): BindingBuilderInterface
    {
        $this->descriptor->setFactory($factoryClass, $method);

        return $this;
    }

    /**
     * @param list<string>|string $tags
     */
    public function tag(array|string $tags): BindingBuilderInterface
    {
        $tags = is_array($tags) ? $tags : [$tags];

        $this->tagManager->tag($this->descriptor->getId(), $tags);

        return $this;
    }

    public function compilerIgnore(): BindingBuilderInterface
    {
        $this->descriptor->compilerIgnore();

        return $this;
    }
}
