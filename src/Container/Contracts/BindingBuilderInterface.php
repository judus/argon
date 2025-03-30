<?php

declare(strict_types=1);

namespace Maduser\Argon\Container\Contracts;

interface BindingBuilderInterface
{
    /**
     * @param class-string $factoryClass
     * @param string|null $method
     * @return BindingBuilderInterface
     */
    public function useFactory(string $factoryClass, ?string $method = null): BindingBuilderInterface;

    public function getDescriptor(): ServiceDescriptorInterface;

    /**
     * @param list<string>|string $tags
     */
    public function tag(array|string $tags): BindingBuilderInterface;
}
