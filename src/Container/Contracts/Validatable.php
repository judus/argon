<?php

namespace Maduser\Argon\Container\Contracts;

interface Validatable
{
    /**
     * Perform validation on the request data.
     *
     * @return bool True if validation passes, false otherwise.
     */
    public function validate(): bool;

    /**
     * Get the validated data after a successful validation.
     *
     * @return array The validated data.
     */
    public function getValidatedData(): array;

    /**
     * Get the validation errors if validation fails.
     *
     * @return array|null An array of validation errors.
     */
    public function getValidationErrors(): ?array;
}