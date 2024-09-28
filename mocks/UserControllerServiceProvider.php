<?php

namespace Maduser\Argon\Mocks;

use Maduser\Argon\Container\ServiceProvider;
use Maduser\Argon\Mocks\UserController;
use ReflectionException;

class UserControllerServiceProvider extends ServiceProvider
{
    /**
     * @return void
     */
    public function register(): void
    {
    }

    /**
     * @return mixed
     * @throws ReflectionException
     */
    public function resolve(): mixed
    {
        return $this->container->make(UserController::class);
    }
}