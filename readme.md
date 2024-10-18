# **Argon Service Container**

A lightweight, PSR-11 compliant dependency injection container.

While it is fully functional, auto-wiring is **not optimized for large scale production environments due to the current reliance on runtime reflection**. Other core features such as manual service binding, lazy loading, singleton handling, and parameter overrides will do just fine for the mid-sized side projects.

## **Features**

- **PSR-11 Compliant**: Integrates with PSR-11 applications.
- **Autowiring**: Automatically resolves class dependencies.
- **Singleton and Transient Services**: Manage shared or independent service instances.
- **Type Interceptors**: Modify instances at resolution time.
- **Parameter Overrides**: Customize service construction with primitive or custom values.
- **Lazy Loading**: Services are only instantiated when first accessed.
- **Circular Dependency Detection**: Automatically detects and prevents circular dependencies.
- **Known Limitations**: Current limitations on performance and missing features (see below).

## **Installation**

```bash
composer require maduser/argon
```
**Requires PHP 8.2+**

## **Usage**

### **1. Binding and Resolving Services**

To bind a service, you provide a **service ID** and the class (or closure) responsible for creating the service. You can define whether the service is **transient** (new instance every time) or **singleton** (same instance for all requests).

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

Need to pass primitive values (like config or custom parameters) into a service? Use **parameter overrides** to inject specific values into the constructor.

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

// This will throw a ContainerException showing highway to hell
$container->get('A');
```

**Note**: Injecting the container itself will cause a circular disaster. I'm aware of the possible "fixes", but I haven't decided which I prefer yet. Here’s the correct way to inject the container:

```php
$container->bind('YourService', function () use ($container) {
    return new YourService($container);
});
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

Tagging allows you to group related services and fetch them as a collection, useful for handling multiple implementations or plugins.

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

The container throws specific exceptions with helpful messages for common issues:

- **`ContainerException`**: Thrown when a service cannot be resolved, a class is uninstantiable, a circular dependency is detected, or when an invalid class or configuration is provided.
- **`NotFoundException`**: Thrown when a requested service is not registered in the container and the class does not exist.

```php
// Handling exceptions
try {
    $container->get('nonExistentService');
} catch (NotFoundException $e) {
    echo $e->getMessage(); // Service 'nonExistentService' not found.
}

try {
    $container->get('UninstantiableClass'); // e.g., an abstract class or circular reference
} catch (ContainerException $e) {
    echo $e->getMessage(); // Class 'UninstantiableClass' is not instantiable, or circular dependency detected.
}
```

## **Known Limitations**

1. **Reflection Overhead**:
   The container currently relies heavily on PHP's reflection APIs for autowiring, which can cause performance degradation, especially in large applications.

2. **No Compile Step**:
   Service definitions are resolved at runtime, which slows down service instantiation compared to containers that compile services into PHP code.

3. **Basic Circular Dependency Handling**:
   Circular dependencies are detected but not resolved, resulting in exceptions. Planned improvements include lazy-loaded proxies to handle circular references smoothly.

---

### **Todos**

- **Compiled Service Definitions**: Implement pre-compiled service definitions to eliminate runtime reflection and improve performance.
- **Dependency Graph Optimization**: Handle complex service dependency graphs more efficiently during the compilation process.
- **Improved Circular Dependency Handling**: Use proxies or lazy services to resolve circular dependencies without throwing exceptions.
- **Expanded Testing Suite**: Write unit tests for more complex edge cases, ensuring stability and reliability in various conditions.
- **Optimize Closure Handling**: Refactor the handling of closures to reduce overhead when resolving services defined as closures.

### **Ultimate goal**

- Optimized for ease of use, while still offering good performance, e.g. no reflections during runtime
- Learn something and have fun :)

## **Tests**

Wanna run the tests? Clone the repository and run:

```bash
vendor/bin/phpunit
```

---

## **License**

This project is licensed under the MIT License.
