<?php

namespace Maduser\Argon\Mocks;

class RequestValidation
{
    use ValidatesInputTrait;

    protected array $data;
    protected array $errors = [];

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * Defines the validation rules for the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [];
    }

    /**
     * Perform validation.
     *
     * @return bool
     * @throws \Exception
     */
    public function validate(): bool
    {
        foreach ($this->rules() as $field => $rule) {
            // Only validate if the field exists in data or the rule is required
            if (isset($this->data[$field]) || str_contains($rule, 'required')) {
                $value = $this->data[$field] ?? null;
                $this->validateField($field, $value, $rule);
            }
        }

        if (!empty($this->errors)) {
            throw new \Exception("Validation failed: " . json_encode($this->errors));
        }

        return true;
    }

    /**
     * Get validated data (only the fields that passed validation).
     *
     * @return array
     */
    public function validated(): array
    {
        return array_filter($this->data, function ($key) {
            return array_key_exists($key, $this->rules());
        }, ARRAY_FILTER_USE_KEY);
    }

    /**
     * Get validation errors.
     *
     * @return array
     */
    public function errors(): array
    {
        return $this->errors;
    }
}
