<?php

declare(strict_types=1);

namespace Maduser\Argon\Container;

use Maduser\Argon\Container\Contracts\BindingBuilderInterface;
use Maduser\Argon\Container\Contracts\ServiceDescriptorInterface;
use Maduser\Argon\Container\Contracts\TagManagerInterface;

/**
 * @inheritDoc
 */
final readonly class BindingBuilder implements BindingBuilderInterface
{
    public function __construct(
        private ServiceDescriptorInterface $descriptor,
        private readonly TagManagerInterface $tagManager,
    ) {
    }

    /** @inheritDoc */
    public function factory(string $factoryClass, ?string $method = null): BindingBuilderInterface
    {
        $this->descriptor->setFactory($factoryClass, $method);

        return $this;
    }

    /** @inheritDoc */
    public function defineInvocation(string $methodName, array $args = []): BindingBuilderInterface
    {
        $this->descriptor->defineInvocation($methodName, $args);

        return $this;
    }

    /** @inheritDoc */
    public function tag(array|string $tags): BindingBuilderInterface
    {
        $tags = is_array($tags) ? $tags : [$tags];

        $this->tagManager->tag($this->descriptor->getId(), $tags);

        return $this;
    }

    /** @inheritDoc */
    public function skipCompilation(): BindingBuilderInterface
    {
        $this->descriptor->skipCompilation();

        return $this;
    }

    /** @inheritDoc */
    public function transient(): BindingBuilderInterface
    {
        $this->descriptor->setShared(false);

        return $this;
    }

    /** @inheritDoc */
    public function shared(): BindingBuilderInterface
    {
        $this->descriptor->setShared(true);

        return $this;
    }

    /** @inheritDoc */
    public function getDescriptor(): ServiceDescriptorInterface
    {
        return $this->descriptor;
    }
}
