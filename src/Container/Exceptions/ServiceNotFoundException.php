<?php 
namespace Maduser\Argon\Container\Exceptions;

use Exception;
use Psr\Container\NotFoundExceptionInterface;

class ServiceNotFoundException extends Exception implements NotFoundExceptionInterface
{
    public function __construct(string $id)
    {
        parent::__construct("Service '$id' not found.");
    }
}