<?php

namespace Maduser\Argon\Container\Exceptions;

use Exception;

class AuthorizationException extends Exception
{
    public function __construct(string $message = "Authorization failed.")
    {
        parent::__construct($message);
    }
}