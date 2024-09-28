<?php

namespace Tests\Integration;

use Exception;
use Maduser\Argon\Container\ServiceContainer;
use Maduser\Argon\Container\ServiceProvider;
use Maduser\Argon\Hooks\HookRequestValidationPostResolution;
use Maduser\Argon\Hooks\HookServiceProviderPostResolution;
use Maduser\Argon\Hooks\HookServiceProviderSetter;
use PHPUnit\Framework\TestCase;
use Tests\App\Request\RequestValidation;
use Tests\Mocks\SomeObject;
use Tests\Mocks\SomeObjectDependsOnSingleton;
use Tests\Mocks\UserControllerServiceProvider;

class ContainerIntegrationTest extends TestCase
{
    protected ServiceContainer $container;

    protected function setUp(): void
    {
        $this->container = new ServiceContainer();

        $this->container->addSetterHook(
            ServiceProvider::class,
            new HookServiceProviderSetter($this->container)
        );

        $this->container->addPostResolutionHook(
            ServiceProvider::class,
            new HookServiceProviderPostResolution($this->container)
        );

        $this->container->addPostResolutionHook(
            RequestValidation::class,
            new HookRequestValidationPostResolution($this->container)
        );
    }

    /**
     * @throws Exception
     */
    public function testUserControllerSingleton()
    {
        // Register UserController as a singleton
        $this->container->singleton('UserController', UserControllerServiceProvider::class);

        // Resolve UserController
        $userController = $this->container->resolve('UserController');
        $userController->setSomeValue('Hello World');

        $this->assertEquals('Hello World', $userController->getSomeValue());

        // Resolve again, should be same singleton instance
        $userController2 = $this->container->resolve('UserController');
        $this->assertSame($userController, $userController2);
        $this->assertEquals('Hello World', $userController2->getSomeValue());
    }

    /**
     * @throws Exception
     */
    public function testSingletonObjectInSomeObject()
    {
        // Register SomeObject as a singleton
        $this->container->singleton(SomeObject::class);

        // Register SomeObjectDependsOnSingleton
        $this->container->register('object', SomeObjectDependsOnSingleton::class);

        /** @var SomeObjectDependsOnSingleton $obj1 */
        $obj1 = $this->container->resolve('object');
        $obj1->singletonObject->value = 10;
        $value1 = $obj1->singletonObject->value;

        /** @var SomeObjectDependsOnSingleton $obj2 */
        $obj2 = $this->container->resolve('object');
        $value2 = $obj2->singletonObject->value;

        // Assert that the singleton object retains the same value
        $this->assertEquals($value1, $value2);
    }
}
