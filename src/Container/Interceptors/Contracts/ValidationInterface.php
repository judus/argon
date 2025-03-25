<?php

declare(strict_types=1);

namespace Maduser\Argon\Container\Interceptors\Contracts;

/**
 * Interface for interceptors that perform validation on a resolved instance.
 */
interface ValidationInterface
{
    /**
     * Execute validation logic.
     */
    public function validate(): void;
}
