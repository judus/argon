<?php 
namespace Maduser\Argon\Container\Exceptions;

use Exception;
use Psr\Container\ContainerExceptionInterface;
use Throwable;

class ContainerErrorException extends Exception implements ContainerExceptionInterface
{
    public function __construct(string $message, Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}