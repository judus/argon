<?php

namespace Tests\Mocks;

use Maduser\Argon\Container\ServiceProvider;
use ReflectionException;

class UserControllerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }

    /**
     * @throws ReflectionException
     */
    public function resolve(): mixed
    {
        return $this->container->make(UserController::class);
    }
}