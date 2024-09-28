<?php

namespace Tests\App\Request;

use Tests\App\Request\Traits\ValidatesInput;

class RequestValidation
{
    use ValidatesInput;

    protected array $data = [];
    protected array $errors = [];

    public function __construct(array $data = [])
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
        $rules = $this->rules();
        foreach ($rules as $field => $rule) {
            $value = $this->data[$field] ?? null;
            $this->validateField($field, $value, $rule);
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
        $rules = $this->rules();

        return array_filter($this->data, function ($key) use ($rules) {
            return array_key_exists($key, $rules);
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