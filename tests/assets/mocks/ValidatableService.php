<?php

namespace Tests\Mocks;

use Maduser\Argon\Container\Contracts\Authorizable;
use Maduser\Argon\Container\Contracts\Validatable;
use Maduser\Argon\Container\Exceptions\AuthorizationException;
use Maduser\Argon\Container\Exceptions\ValidationException;

class ValidatableService implements Validatable, Authorizable
{
    private array $data;
    private array $errors = [];
    private bool $isValid = false;
    private bool $isAuthorized = false;

    public function __construct(array $data)
    {
        $this->data = $data;
        $this->errors = [];
    }

    /**
     * @throws AuthorizationException
     */
    public function authorize(): bool
    {
        // Dummy logic to authorize
        if ($this->data['role'] !== 'admin') {
            throw new AuthorizationException('You are not allowed to perform this action.');
        }

        $this->isAuthorized = true;

        return $this->isAuthorized;
    }

    /**
     * @throws ValidationException
     */
    public function validate(): bool
    {
        if (empty($this->data['name'])) {
            $this->errors['name'] = 'The name field is required.';
        }

        if (!empty($this->errors)) {
            throw new ValidationException($this->errors);
        }

        $this->isValid = true;

        return $this->isValid;
    }

    /**
     * @throws ValidationException
     */
    public function getValidatedData(): array
    {
        if (!$this->isValid) {
            throw new ValidationException(['message' => 'Data has not been validated.']);
        }

        return $this->data;
    }

    public function getValidationErrors(): ?array
    {
        return $this->errors;
    }

    /**
     * @return array|null
     */
    public function getAuthorizationErrors(): ?array
    {
        // TODO: Implement getAuthorizationErrors() method.
    }
}