<?php
declare(strict_types=1);

namespace Maduser\Argon\Container;

class ParameterOverrideRegistry
{
    /**
     * Holds parameter overrides, structured as [className => [paramName => value]]
     */
    private array $overrides = [];

    /**
     * Set an override for a specific class and parameter.
     *
     * @param string $className The class where the parameter belongs
     * @param string $paramName The parameter to override
     * @param mixed  $value     The value to inject
     */
    public function setOverride(string $className, string $paramName, mixed $value): void
    {
        $this->overrides[$className][$paramName] = $value;
    }

    /**
     * Get all overrides for a given class.
     *
     * @param string $className The class for which to retrieve overrides
     *
     * @return array The array of parameter overrides
     */
    public function getOverridesForClass(string $className): array
    {
        return $this->overrides[$className] ?? [];
    }

    /**
     * Checks if there is an override for a specific class and parameter.
     *
     * @param string $className The class where the parameter belongs
     * @param string $paramName The parameter to check
     *
     * @return bool Whether an override exists
     */
    public function hasOverride(string $className, string $paramName): bool
    {
        return isset($this->overrides[$className][$paramName]);
    }

    /**
     * Get a specific override for a class parameter.
     *
     * @param string $className The class where the parameter belongs
     * @param string $paramName The parameter to get the override for
     *
     * @return mixed The override value
     */
    public function getOverride(string $className, string $paramName): mixed
    {
        return $this->overrides[$className][$paramName] ?? null;
    }
}
