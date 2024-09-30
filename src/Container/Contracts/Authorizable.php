<?php

namespace Maduser\Argon\Container\Contracts;

interface Authorizable
{
    /**
     * Check if the current user is authorized to perform a certain action.
     *
     * @return bool True if authorized, false otherwise.
     */
    public function authorize(): bool;

    /**
     * Get the authorization errors if authorization fails.
     *
     * @return array|null An array of authorization errors.
     */
    public function getAuthorizationErrors(): ?array;
}