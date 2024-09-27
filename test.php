<?php

namespace App;

use Maduser\Argon\Container\ServiceContainer;
use Maduser\Argon\Container\ServiceProvider;
use Maduser\Argon\Hooks\HookServiceProviderPostResolution;
use Maduser\Argon\Hooks\HookServiceProviderSetter;
use Maduser\Argon\Hooks\HookRequestValidationPostResolution;

require_once 'vendor/autoload.php';
require_once '../datastore-app/maduser/Support/debug.php';

$container = new ServiceContainer();
$container->addSetterHook(ServiceProvider::class, new HookServiceProviderSetter($container));
$container->addPostResolutionHook(RequestValidation::class, new HookRequestValidationPostResolution($container));
$container->addPostResolutionHook(ServiceProvider::class, new HookServiceProviderPostResolution($container));

class SingletonObject
{
    public int $value = 0;

    public function __construct() {}
}

class SomeObject
{
    public SingletonObject $singletonObject;

    public function __construct(SingletonObject $singletonObject)
    {
        $this->singletonObject = $singletonObject;
    }
}

class RequestValidation {
    public function validate()
    {
        dump('RequestValidation::validate()');
    }
}

class SaveUserRequest extends RequestValidation
{
    public function __construct()
    {
        dump('SaveUserRequest created');
    }
}

class UserController
{
    private SaveUserRequest $request;

    private string $someValue = 'Hello';

    public function __construct(SaveUserRequest $request)
    {
        $this->request = $request;
    }

    public function action()
    {
        dump('UserController::action()');
        return $this->request;
    }

    public function setSomeValue(string $value): void
    {
        $this->someValue = $value;
    }

    public function getSomeValue(): string
    {
        return $this->someValue;
    }
}


class UserControllerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        dump('UserControllerServiceProvider::register()');
    }

    public function resolve(): mixed
    {
        return $this->container->make(UserController::class);
    }
}

$container->singleton('UserController', UserControllerServiceProvider::class);

$userController = $container->resolve('UserController');
$userController->setSomeValue('Hello World');

$userController->action();

$userController2 = $container->resolve('UserController');
echo $userController2->getSomeValue(). PHP_EOL; // Hello World



    dump('------------------------------------------');



$container->singleton(SingletonObject::class);
$container->register('some-object', SomeObject::class);

$obj1 = $container->resolve('some-object');
$obj1->singletonObject->value = 10;

$obj2 = $container->resolve('some-object');
echo $obj2->singletonObject->value . PHP_EOL; // 10

