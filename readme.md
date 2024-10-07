
# **Argon Service Container**

A lightweight, PSR-11 compliant dependency injection container.

## **Features**

- **PSR-11 Compliant**: Integrates with PSR-11 applications.
- **Autowiring**: Automatically resolves class dependencies.
- **Singleton and Transient Services**: Manage shared or independent service instances.
- **Type Interceptors**: Modify instances at resolution time.
- **Parameter Overrides**: Customize service construction with primitive or custom values.
- **Lazy Loading**: Services are only instantiated when first accessed.
- **Circular Dependency Detection**: Automatically detects and prevents circular dependencies.

## **Installation**

```bash
composer require maduser/argon
```
**Reuquires PHP 8.2+** 

## **Usage**

### **1. Binding and Resolving Services**

To bind a service, you provide a **service ID** and the class (or closure) responsible for creating the service. You can
define whether the service is **transient** (new instance every time) or **singleton** (same instance for all requests).

```php
use Maduser\Argon\Container\ServiceContainer;

$container = new ServiceContainer();

// Transient service: creates a new instance every time
$container->bind('service1', \App\Services\SomeService::class);

// Singleton service: returns the same instance for every request
$container->singleton('service2', \App\Services\AnotherService::class);

// Fetch the services
$service1 = $container->get('service1');
$service2 = $container->get('service2');
```

### **2. Autowiring Services**

The container can resolve dependencies based on constructor signatures without explicit bindings.

```php
class LoggerService {}

class UserService {
    private LoggerService $logger;

    public function __construct(LoggerService $logger) {
        $this->logger = $logger;
    }

    public function logUserAction(string $action): void {
        $this->logger->log("User action: {$action}");
    }
}

$container = new ServiceContainer();

// No need to bind anything explicitly; autowiring resolves the dependency
$userService = $container->get(UserService::class);
```

### **3. Parameter Overrides**

Need to pass primitive values (like config or custom parameters) into a service? Use **parameter overrides** to inject
specific values into the constructor.

```php
class ApiClient {
    public function __construct(string $apiKey, string $apiUrl) {}
}

$overrideRegistry = new \Maduser\Argon\Container\ParameterOverrideRegistry();
$overrideRegistry->addOverride(ApiClient::class, 'apiKey', 'my-secret-key');
$overrideRegistry->addOverride(ApiClient::class, 'apiUrl', 'https://api.example.com');

$container = new ServiceContainer($overrideRegistry);

// ApiClient will receive the overridden parameters
$apiClient = $container->get(ApiClient::class);
```

### **4. Handling Circular Dependencies**

The container detects and blocks circular dependencies to prevent infinite loops.

```php
$container->singleton('A', function () use ($container) {
    return $container->get('B');
});

$container->singleton('B', function () use ($container) {
    return $container->get('A');
});

// This will throw a CircularDependencyException
$container->get('A');
```

### **5. Type Interceptors**

Interceptors can be used to modify or decorate instances when they're resolved. 

```php
class AuthService {
    private string $user;

    public function setUser(string $user): void {
        $this->user = $user;
    }
}

// Interceptor to dynamically modify AuthService
class AuthInterceptor implements TypeInterceptorInterface {
    public function supports(object $instance): bool {
        return $instance instanceof AuthService;
    }

    public function intercept(object $instance): object {
        $instance->setUser('interceptedUser');
        return $instance;
    }
}

$container = new ServiceContainer();
$container->registerTypeInterceptor(new AuthInterceptor());

$authService = $container->get(AuthService::class);
// The AuthService now has 'interceptedUser' set
```

### **6. Tagging and Retrieving Services**

Tagging allows you to group related services and fetch them as a collection, useful for handling multiple
implementations or plugins.

```php
$container->singleton('logger1', \App\Loggers\FileLogger::class);
$container->singleton('logger2', \App\Loggers\DatabaseLogger::class);

$container->tag('logger1', ['loggers']);
$container->tag('logger2', ['loggers']);

// Retrieve all services tagged with 'loggers'
$loggers = $container->getTaggedServices('loggers');

foreach ($loggers as $logger) {
    $logger->log('A message to all loggers');
}
```

### **7. Lazy Loading Services**

Services are not instantiated until they are actually used.

```php
$container->singleton('expensiveService', function () {
    return new \App\Services\HeavyLiftingService();
});

// HeavyLiftingService is only created when it's requested
$service = $container->get('expensiveService');
```

## **Exception Handling**

The container throws specific exceptions for common issues:

- **`CircularDependencyException`**: Thrown when a circular dependency is detected.
- **`ContainerException`**: Thrown when a service cannot be resolved or an invalid class is provided.
- **`NotFoundException`**: Thrown when a requested service is not registered in the container.

```php
// Handling exceptions
try {
    $container->get('nonExistentService');
} catch (NotFoundException $e) {
    echo $e->getMessage(); // Service 'nonExistentService' not found.
}
```

## **Tests**

Wanna run the tests? Clone the repository and run:

```bash
vendor/bin/phpunit
```

---

## **License**

This project is licensed under the MIT License.
