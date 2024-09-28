# Argon Framework

A lightweight PHP framework that provides a robust and flexible way to manage services, singletons, bindings, and
service providers with support for dependency injection and resolution.

## Overview

The Argon Framework is designed to simplify the development of PHP applications by providing a lightweight and modular
structure. It offers features such as:

- **Service Registration**: Register services and singletons.
- **Dependency Injection**: Automatically inject dependencies via constructor injection.
- **Service Providers**: Modularize service registration and bootstrapping.
- **Bindings**: Bind interfaces to concrete implementations.
- **Hooks**: Pre- and post-resolution hooks for custom behavior.
- **Kernel Management**: Abstract kernel for application bootstrapping and error handling.
- **Error Handling**: Centralized error and exception handling.
- **CLI Application Support**: Build command-line applications with ease.
- **Facades**: Simplify access to container services through static interfaces.

## Requirements

- PHP **>= 8.2**

## Installation

Install via Composer:

```bash
composer require argon/framework
```

## Getting Started

### Creating the Container

```php
use Maduser\Argon\Container\ServiceContainer;

$container = new ServiceContainer();
```

### Registering Services

#### Registering a Service

```php
$container->register('serviceAlias', SomeClass::class);
```

#### Registering Multiple Services

```php
$container->register([
    'serviceAlias1' => SomeClass::class,
    'serviceAlias2' => AnotherClass::class,
]);
```

#### Registering a Singleton

```php
$container->singleton('singletonAlias', SomeSingletonClass::class);
```

### Binding Interfaces

Bind an interface to a concrete implementation:

```php
$container->bind(InterfaceName::class, ConcreteClass::class);
```

Or bind multiple interfaces:

```php
$container->bind([
    InterfaceOne::class => ConcreteOne::class,
    InterfaceTwo::class => ConcreteTwo::class,
]);
```

### Resolving Services

```php
$service = $container->resolve('serviceAlias');
```

### Dependency Injection

Dependencies are automatically injected via constructor injection when resolving services.

```php
class SomeClass {
    public function __construct(DependencyClass $dependency) {
        // ...
    }
}

$instance = $container->resolve(SomeClass::class);
```

### Using Service Providers

Create a service provider by extending `ServiceProvider`:

```php
use Maduser\Argon\Container\ServiceProvider;

class MyServiceProvider extends ServiceProvider {
    public function register() {
        $this->container->register('myService', MyService::class);
    }

    public function resolve() {
        return $this->container->resolve('myService');
    }
}
```

Register the service provider:

```php
$container->register('myServiceProvider', MyServiceProvider::class);
```

### Using Hooks

Add pre- and post-resolution hooks:

```php
// Pre-resolution hook
$container->addPreResolutionHook(SomeInterface::class, function($descriptor, $params) {
    // Custom logic before resolution
});

// Post-resolution hook
$container->addPostResolutionHook(SomeInterface::class, function($instance, $descriptor) {
    // Custom logic after resolution
    return $instance;
});
```

### Auto-Resolving Unregistered Classes

By default, the container can auto-resolve classes that are not explicitly registered:

```php
$container->setAutoResolveUnregistered(true);

$instance = $container->resolveOrMake(UnregisteredClass::class);
```

### Example Usage

Below is an example demonstrating how to use the Argon Framework to manage services, singletons, service providers, and
hooks.

```php
<?php

namespace App;

use Maduser\Argon\Container\ServiceContainer;
use Maduser\Argon\Container\ServiceProvider;
use Maduser\Argon\Hooks\HookServiceProviderPostResolution;
use Maduser\Argon\Hooks\HookServiceProviderSetter;
use Maduser\Argon\Hooks\HookRequestValidationPostResolution;

require_once 'vendor/autoload.php';

// Create a new ServiceContainer instance
$container = new ServiceContainer();

// Add hooks to the container
$container->addSetterHook(ServiceProvider::class, new HookServiceProviderSetter($container));
$container->addPostResolutionHook(RequestValidation::class, new HookRequestValidationPostResolution($container));
$container->addPostResolutionHook(ServiceProvider::class, new HookServiceProviderPostResolution($container));

// Define classes
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
        // Validation logic here
    }
}

class SaveUserRequest extends RequestValidation
{
    public function __construct()
    {
        // Initialization logic here
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
        // Controller action logic here
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
        // Registration logic here
    }

    public function resolve(): mixed
    {
        return $this->container->make(UserController::class);
    }
}

// Register the UserController as a singleton using a ServiceProvider
$container->singleton('UserController', UserControllerServiceProvider::class);

// Resolve the UserController from the container
$userController = $container->resolve('UserController');
$userController->setSomeValue('Hello World');
$userController->action();

// Resolve the UserController again to demonstrate singleton behavior
$userController2 = $container->resolve('UserController');
echo $userController2->getSomeValue(); // Outputs: Hello World

// Demonstrate singleton object sharing
$container->singleton(SingletonObject::class);
$container->register('some-object', SomeObject::class);

$obj1 = $container->resolve('some-object');
$obj1->singletonObject->value = 10;

$obj2 = $container->resolve('some-object');
echo $obj2->singletonObject->value; // Outputs: 10
```

**Explanation:**

- **ServiceContainer Initialization**: A new service container is created to manage services.
- **Adding Hooks**: Hooks are added to handle service providers and request validation post-resolution.
- **Defining Classes**: Various classes are defined, including a `SingletonObject`, `SomeObject`, and `UserController`.
- **ServiceProvider Usage**: A `UserControllerServiceProvider` is created to manage the registration and resolution of
  the `UserController`.
- **Registering Services**: The `UserController` is registered as a singleton, and `SomeObject` is registered normally.
- **Resolving Services**: Services are resolved from the container, demonstrating singleton behavior and dependency
  injection.

### Building CLI Applications

The Argon Framework includes support for building command-line applications through the `CliApp` class.

#### Creating a CLI Application

```php
use Maduser\Argon\Container\ServiceContainer;
use Maduser\Argon\Kernel\EnvApp\CliApp;

$container = new ServiceContainer();
$cliApp = new CliApp($container);

// Boot the kernel (register services, providers, etc.)
$cliApp->bootKernel();

// Handle the CLI application
$cliApp->handle();
```

#### Defining Commands

Commands can be managed using the `CommandManager` and `Console` classes from the `maduser/console` package.

1. **Install the Console Package**:

   ```bash
   composer require maduser/console
   ```

2. **Register the Console and CommandManager**:

   The `CliApp` class automatically registers the `Console` and `CommandManager` if they exist.

3. **Create Commands**:

   ```php
   use Maduser\Console\Command;

   class MyCommand extends Command {
       public function execute() {
           $this->console->writeLine('Hello from MyCommand!');
       }
   }
   ```

4. **Register Commands**:

   ```php
   $commandManager = $container->resolve('CommandManager');
   $commandManager->register('mycommand', MyCommand::class);
   ```

5. **Run the CLI Application**:

   ```php
   $cliApp->handle();
   ```

   Now you can execute your command via the command line:

   ```bash
   php script.php mycommand
   ```

### Using the Facades

The Argon Framework provides facades for simplifying access to container services through static interfaces.

#### Using the `Container` Facade

The `Container` facade provides static methods to interact with the service container.

```php
use Maduser\Argon\Container;

// Register a service
Container::register('serviceAlias', SomeClass::class);

// Resolve a service
$service = Container::resolve('serviceAlias');

// Create an instance without registering
$instance = Container::make(SomeClass::class);
```

#### Using the `App` Facade

The `App` facade extends the `Container` facade and provides methods for initializing and running the application.

```php
use Maduser\Argon\App;

// Initialize the application
App::init();

// Run the application
App::run();

// Or chain the methods
App::init()->run();
```

**Dispatching a Callback in an Isolated Context**

```php
App::dispatch(function() {
    // Your callback logic here
});
```

**Accessing the Kernel**

```php
$kernel = App::getKernel();
```

## Development

### Requirements

- PHP **>= 8.2**

### Installation for Development

Clone the repository and install dependencies:

```bash
git clone https://github.com/yourusername/argon-framework.git
cd argon-framework
composer install
```

### Running Tests

The framework uses PHPUnit for unit testing. To run the tests:

```bash
composer test
```

### Static Analysis

Psalm is used for static code analysis. To check for errors:

```bash
composer check
```

### Code Style

PHP_CodeSniffer is used to ensure code style consistency. To automatically fix code style issues:

```bash
composer fix
```

### Watching for Changes

You can watch for file changes and automatically run tests or checks:

- **Watch and Test**:

  ```bash
  composer watch-test
  ```

- **Watch and Fix**:

  ```bash
  composer watch-fix
  ```

### Scripts

The `composer.json` includes several useful scripts:

- **Test**: Runs PHPUnit tests.

  ```bash
  composer test
  ```

- **Check**: Runs tests, Psalm static analysis, and PHP_CodeSniffer.

  ```bash
  composer check
  ```

- **Fix**: Attempts to automatically fix issues found by Psalm and PHP_CodeSniffer.

  ```bash
  composer fix
  ```

## Contributing

Contributions are welcome! Please follow the guidelines below:

1. **Fork the Repository**: Click the "Fork" button at the top-right corner of the repository page.

2. **Create a New Branch**: It's good practice to create a new branch for each feature or bugfix.

   ```bash
   git checkout -b feature/my-new-feature
   ```

3. **Write Tests**: Ensure that your code changes are covered by unit tests.

4. **Run Checks**: Before submitting, make sure all checks pass.

   ```bash
   composer check
   ```

5. **Submit a Pull Request**: Once your changes are ready, submit a pull request for review.

## License

This project is licensed under the MIT License.

## Author

- **Julien Duseyau**
