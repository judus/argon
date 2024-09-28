<?php

namespace Tests\App\Request\Traits;

trait ValidatesInput
{
    /**
     * Validate a single field against the defined rules.
     *
     * @param string       $field
     * @param mixed        $value
     * @param string|array $rules
     *
     * @return void
     */
    protected function validateField(string $field, $value, $rules)
    {
        $rules = is_array($rules) ? $rules : explode('|', $rules);

        // If the field is not required and not present in the data, skip validation
        if (!isset($this->data[$field]) && !in_array('required', $rules)) {
            return;
        }

        foreach ($rules as $rule) {
            if (method_exists($this, $rule)) {
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
    protected function required(string $field, $value): void
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
    protected function integer(string $field, $value): void
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
    protected function string(string $field, $value): void
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
    protected function email(string $field, $value): void
    {
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->errors[$field][] = "$field must be a valid email address.";
        }
    }
}
