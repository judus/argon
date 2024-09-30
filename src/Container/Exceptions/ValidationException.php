<?php

namespace Maduser\Argon\Container\Exceptions;

use Exception;

class ValidationException extends Exception
{
    /**
     * @var array Validation errors
     */
    protected array $errors;

    /**
     * ValidationException constructor.
     *
     * @param array          $errors   The validation errors
     * @param int            $code     The exception code (optional)
     * @param Exception|null $previous The previous exception (optional)
     */
    public function __construct(array $errors, int $code = 0, ?Exception $previous = null)
    {
        $this->errors = $errors;
        $message = 'Validation failed with errors: ' . json_encode($errors);
        parent::__construct($message, $code, $previous);
    }

    /**
     * Get the validation errors.
     *
     * @return array
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
