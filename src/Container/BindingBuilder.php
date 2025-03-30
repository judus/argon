<?php

declare(strict_types=1);

namespace Maduser\Argon\Container;

final class BindingBuilder
{
    public function __construct(
        private ServiceDescriptor $descriptor
    ) {
    }

    public function useFactory(string $factoryClass, ?string $method = null): self
    {
        $this->descriptor->setFactory($factoryClass, $method);
        return $this;
    }

    public function getDescriptor(): ServiceDescriptor
    {
        return $this->descriptor;
    }
}
