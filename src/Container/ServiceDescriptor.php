<?php

namespace Maduser\Argon\Container;

/**
 * Class ServiceDescriptor
 *
 * Represents a service definition including metadata such as singleton status and default parameters.
 *
 * @package Maduser\Argon\Container
 */
class ServiceDescriptor
{
    private string $className;
    private bool $isSingleton;
    private ?array $defaultParams;

    /**
     * ServiceDescriptor constructor.
     *
     * @param string     $className     The name of the class
     * @param bool       $isSingleton   Whether the service should be a singleton
     * @param array|null $defaultParams Optional default parameters for instantiation
     */
    public function __construct(string $className, bool $isSingleton = false, ?array $defaultParams = null)
    {
        $this->className = $className;
        $this->isSingleton = $isSingleton;
        $this->defaultParams = $defaultParams;
    }

    /**
     * Get the class name of the service.
     *
     * @return string
     */
    public function getClassName(): string
    {
        return $this->className;
    }

    /**
     * Check if the service is a singleton.
     *
     * @return bool
     */
    public function isSingleton(): bool
    {
        return $this->isSingleton;
    }

    /**
     * Get the default parameters for the service.
     *
     * @return array|null
     */
    public function getDefaultParams(): ?array
    {
        return $this->defaultParams;
    }
}
