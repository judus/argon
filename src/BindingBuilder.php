<?php

declare(strict_types=1);

namespace Maduser\Argon\Container;

use Maduser\Argon\Container\Contracts\BindingBuilderInterface;
use Maduser\Argon\Container\Contracts\ServiceDescriptorInterface;
use Maduser\Argon\Container\Contracts\TagManagerInterface;
use Override;

/**
 * @inheritDoc
 */
final readonly class BindingBuilder implements BindingBuilderInterface
{
    public function __construct(
        private ServiceDescriptorInterface $descriptor,
        private TagManagerInterface $tagManager,
    ) {
    }

    /** @inheritDoc */
    #[Override]
    public function factory(string $factoryClass, ?string $method = null): BindingBuilderInterface
    {
        $this->descriptor->setFactory($factoryClass, $method);

        return $this;
    }

    /** @inheritDoc */
    #[Override]
    public function defineInvocation(string $methodName, array $args = []): BindingBuilderInterface
    {
        $this->descriptor->defineInvocation($methodName, $args);

        return $this;
    }

    /** @inheritDoc */
    #[Override]
    public function tag(array|string $tags): BindingBuilderInterface
    {
        $tags = is_array($tags) ? $tags : [$tags];

        $this->tagManager->tag($this->descriptor->getId(), $tags);

        return $this;
    }

    /** @inheritDoc */
    #[Override]
    public function skipCompilation(): BindingBuilderInterface
    {
        $this->descriptor->skipCompilation();

        return $this;
    }

    /** @inheritDoc */
    #[Override]
    public function transient(): BindingBuilderInterface
    {
        $this->descriptor->setShared(false);

        return $this;
    }

    /** @inheritDoc */
    #[Override]
    public function shared(): BindingBuilderInterface
    {
        $this->descriptor->setShared(true);

        return $this;
    }

    /** @inheritDoc */
    #[Override]
    public function getDescriptor(): ServiceDescriptorInterface
    {
        return $this->descriptor;
    }
}
