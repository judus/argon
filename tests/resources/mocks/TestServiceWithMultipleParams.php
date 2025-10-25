<?php

declare(strict_types=1);

namespace Tests\Mocks;

/**
 * Mock service class with multiple parameters for testing overrides.
 */
final class TestServiceWithMultipleParams
{
    private string $param1;
    private string $param2;

    /**
     * Constructor that accepts multiple parameters.
     *
     * @param string $param1 The first parameter.
     * @param string $param2 The second parameter.
     */
    public function __construct(string $param1, string $param2)
    {
        $this->param1 = $param1;
        $this->param2 = $param2;
    }

    /**
     * Getter for param1.
     *
     * @return string
     */
    public function getParam1(): string
    {
        return $this->param1;
    }

    /**
     * Getter for param2.
     *
     * @return string
     */
    public function getParam2(): string
    {
        return $this->param2;
    }
}
