<?php

namespace App;

use Maduser\Argon\Container\Hooks\HookRequestValidationPostResolution;
use Maduser\Argon\Container\Hooks\HookServiceProviderPostResolution;
use Maduser\Argon\Container\Hooks\HookServiceProviderSetup;
use Maduser\Argon\Container\ServiceContainer;
use Maduser\Argon\Container\ServiceProvider;

require_once '../../vendor/autoload.php';
require_once '../../../datastore-app/maduser/Support/debug.php';

$container = new ServiceContainer();
$container->addSetterHook(ServiceProvider::class, new HookServiceProviderSetup($container));
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

    public function getSomeValue(): string
    {
        return $this->someValue;
    }

    public function setSomeValue(string $value): void
    {
        $this->someValue = $value;
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

$userController = $container->get('UserController');
$userController->setSomeValue('Hello World');

$userController->action();

$userController2 = $container->get('UserController');
echo $userController2->getSomeValue(). PHP_EOL; // Hello World

dump('------------------------------------------');

$container->singleton(SingletonObject::class);
$container->set('some-object', SomeObject::class);

$obj1 = $container->get('some-object');
$obj1->singletonObject->value = 10;

$obj2 = $container->get('some-object');
echo $obj2->singletonObject->value . PHP_EOL; // 10

