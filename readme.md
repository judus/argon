# Argon Service Container

Argon Service Container is a lightweight, powerful, and flexible dependency injection container designed to manage services, bindings, singletons, providers, and hooks. It supports automatic resolution of dependencies, service providers, validation, authorization, and tagging of services for better organization.

## Features

- **Service Registration & Resolution**: Register and resolve services, singletons, and service providers.
- **Dependency Injection (DI)**: Automatically inject dependencies.
- **Service Providers**: Register, configure and resolve multiple services at once in a provider.
- **Service Tagging**: Tag services and retrieve them based on tags.
- **Lazy Loading**: Services are only instantiated when needed.
- **Hooks**: Lifecycle hooks for custom behavior.
- **Validation & Authorization**: Validates/authorizes upon service instantiation.

## Usage Examples

Examples demonstrating the main features of the container.

### Basic Service Registration & Singleton

```php
$container = new ServiceContainer();

// Binding a service
$container->set('logger', FileLogger::class);

// Registering a singleton
$container->singleton('databaseLogger', function () {
    return new DatabaseLogger();
});

// Using bindings
$container->bind(LoggerInterface::class, DatabaseLogger::class);

// Resolving services
$logger = $container->get('logger');
$logger->info("Test log to file");

// Singleton verification
$singleton1 = $container->get('databaseLogger');
$singleton2 = $container->get('databaseLogger');
echo ($singleton1 === $singleton2) ? "Singleton works, same instance returned." : "Singleton failed.";
```

### Dependency Injection (DI)

Dependency injection is supported automatically when resolving services. The container will inject any required
dependencies.

```php
$someService = new SomeService($container->get(LoggerInterface::class));
$someService->doSomething('Some service with injected logger');

// Auto-resolution
$someService2 = $container->make(SomeService::class);
$someService2->doSomething("Some service with auto resolution");
```

### Service Provider Example

You can register multiple services in a provider and resolve them as a group.

```php
$container->set('SomeProvidedService', SomeProvider::class);

// Resolving services provided by SomeProvider
$someProvidedServices = $container->get('SomeProvidedService');
echo sprintf("SomeProvidedService returned %s service(s): ", count($someProvidedServices));
var_dump($someProvidedServices);
```

### Closure-based Service Registration

Services can be registered using closures, which allows for more flexible instantiation logic.

```php
$container->set('someService', SomeService::class);
$container->get('someService')->doSomething('Some message');

$container->set('someOtherService', function () use ($container) {
    return new SomeService($container->get(LoggerInterface::class));
});
$container->get('someOtherService')->doSomething('Another message');
```

### Validation & Authorization Example

The container supports services implementing `Validatable` and `Authorizable` interfaces, with pre-configured hooks for
automatic validation and authorization.

```php
try {
    $container->set('exampleService1', function () {
        return new ValidatableService([
            'name' => 'John Doe',
            'role' => 'admin',
        ]);
    });
    $exampleService1 = $container->get('exampleService1');
    $data = $exampleService1->getValidatedData();
    echo "Validated data: " . print_r($data, true);
    echo "Service authorized successfully.";
} catch (ValidationException $e) {
    echo "Validation errors: " . print_r($e->getErrors(), true);
} catch (AuthorizationException $e) {
    echo $e->getMessage();
}
```

### Tagging Services

You can tag services with specific tags and retrieve them later by their tag.

```php
$container->set('someService', SomeService::class);
$container->tag('someService', ['utility', 'logger']);

$container->set('anotherService', AnotherService::class);
$container->tag('anotherService', ['utility']);

$utilityServices = $container->tagged('utility');
$loggerServices = $container->tagged('logger');
echo sprintf('There are %s utility and %s logger services', count($utilityServices), count($loggerServices));
```

### Nullifier (`if()`)

Using the `if()` method, won't throw an exception if the service does not exist.

```php
$container->if('someService')->doSomething('Some message'); // Executes if 'someService' exists
$container->if('foo')->doSomething('Some message'); // Does nothing, as 'foo' does not exist
```

#### If you run the examples above, you should see the following output:

```plaintext
INFO: Test log to file
INFO: Test log to database
Singleton works, same instance returned.
INFO: Some service with injected logger
INFO: Some service with auto resolution
SomeProvidedService returned 2 service(s):
array(2) {
  ["SomeObject"]=>
  object(SomeService)#...
  ["AnotherObject"]=>
  object(SomeService)#...
}
INFO: Some message
INFO: Another message
INFO: Final message
Validated data: array(
  "name" => "John Doe",
  "role" => "admin"
)
Service authorized successfully.
There are 2 utility and 1 logger services
```
