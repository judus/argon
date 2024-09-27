<?php

namespace Maduser\Argon\Tests\unit\Container;

use Exception;
use Maduser\Argon\Container\ServiceContainer;
use Maduser\Argon\Container\ServiceProvider;
use Maduser\Argon\Hooks\HookRequestValidationPostResolution;
use Maduser\Argon\Hooks\HookServiceProviderPostResolution;
use Maduser\Argon\Hooks\HookServiceProviderSetter;
use Maduser\Argon\mocks\RequestValidation;
use Maduser\Argon\Mocks\SingletonObject;
use Maduser\Argon\Mocks\SomeObject;
use Maduser\Argon\Mocks\UserControllerServiceProvider;
use PHPUnit\Framework\TestCase;

class ContainerIntegrationTest extends TestCase
{
    protected ServiceContainer $container;

    protected function setUp(): void
    {
        $this->container = new ServiceContainer();

        $this->container->addSetterHook(ServiceProvider::class,
            new HookServiceProviderSetter($this->container));

        $this->container->addPostResolutionHook(ServiceProvider::class,
            new HookServiceProviderPostResolution($this->container));

        $this->container->addPostResolutionHook(RequestValidation::class,
            new HookRequestValidationPostResolution($this->container));
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
        // Register SingletonObject as a singleton
        $this->container->singleton(SingletonObject::class);

        // Register SomeObject
        $this->container->register('some-object', SomeObject::class);

        /** @var SomeObject $obj1 */
        $obj1 = $this->container->resolve('some-object');
        $obj1->singletonObject->value = 10;

        $obj2 = $this->container->resolve('some-object');

        // Assert that the singleton object retains the same value
        $this->assertEquals(10, $obj2->singletonObject->value);
    }


}
