<?php

namespace Maduser\Argon\Mocks;

trait ValidatesInputTrait
{
    /**
     * Validate a single field against the defined rules.
     *
     * @param string       $field
     * @param mixed        $value
     * @param array|string $rules
     */
    protected function validateField(string $field, mixed $value, array|string $rules): void
    {
        $rules = is_array($rules) ? $rules : explode('|', $rules);

        // If the field is not required and not present in the data, skip validation
        if (!isset($this->data[$field]) && !in_array('required', $rules)) {
            return;
        }

        foreach ($rules as $rule) {
            if (method_exists($this, $rule)) {
                // Collect all errors, don't stop at the first one
                $this->$rule($field, $value);
            }
        }
    }

    /**
     * Example rule: Required
     *
     * @param string $field
     * @param mixed  $value
     */
    protected function required(string $field, mixed $value): void
    {
        if (is_null($value) || trim($value) === '') {
            $this->errors[$field][] = "$field is required.";
        }
    }

    /**
     * Example rule: Integer
     *
     * @param string $field
     * @param mixed  $value
     */
    protected function integer(string $field, mixed $value): void
    {
        if (!is_numeric($value)) {
            $this->errors[$field][] = "$field must be an integer.";
        }
    }

    /**
     * Example rule: String
     *
     * @param string $field
     * @param mixed  $value
     */
    protected function string(string $field, mixed $value): void
    {
        if (!is_string($value)) {
            $this->errors[$field][] = "$field must be a string.";
        }
    }

    /**
     * Example rule: Email
     *
     * @param string $field
     * @param mixed  $value
     */
    protected function email(string $field, mixed $value): void
    {
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->errors[$field][] = "$field must be a valid email address.";
        }
    }
}
