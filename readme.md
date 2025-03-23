# Argon Service Container

A high-performance, PSR-11 compliant dependency injection container for modern PHP applications.

Argon focuses on ease of use without compromising features, performance, or flexibility. It combines intuitive service binding with blazing-fast compiled resolution, and supports powerful service lifecycle extensions via type interceptors.

---

## Features

- **🔥 Compilable**: Eliminate runtime reflection entirely with precompiled service definitions.
- **⚙️ PSR-11 Compliant**: Drop-in compatibility with standard PSR-11 containers.
- **🧠 Autowiring**: Automatically resolve dependencies using constructor signatures.
- **♻️ Singleton & Transient Services**: Use shared or separate instances per request.
- **🧩 Parameter Overrides**: Inject primitives and custom values into your services.
- **🛠 Type Interceptors**: Apply behavior-modifying logic when services are resolved.
- **⏱ Lazy Loading**: Services are only instantiated when first accessed.
- **🚨 Circular Dependency Detection**: Detects and protects against infinite resolution loops.

---

## Installation

```bash
composer require maduser/argon
```

Requires PHP 8.2+

### Tests

```bash
vendor/bin/phpunit
```

---

## Usage

### 1. Binding and Resolving Services

```php
// Simple transient registration
$container->bind(MyService::class, MyService::class);
$container->bind(MyService::class); // shortcut, same as above

// Simple singleton registration (all do the same thing)
$container->singleton(MyOtherService::class);
$container->singleton(MyOtherService::class, MyOtherService::class);
$container->bind(MyOtherService::class, MyOtherService::class, true);

// Bind an interface to a concrete implementation
$container->bind(LoggerInterface::class, FileLogger::class);
$container->singleton(LoggerInterface::class, DatabaseLogger::class);

// Resolve service
$transientService = $container->get(MyService::class);
$singletonService = $container->get(MyOtherService::class);
$implementation = $container->get(LoggerInterface::class);
```

### 2. Autowiring

```php
class Logger {}

class UserService {
    public function __construct(Logger $logger) {}
}

$container->get(UserService::class); // Works out of the box
```

### 3. Parameter Overrides

```php
class ApiClient {
    public function __construct(string $apiKey, string $apiUrl) {}
}

$container->getParameters()->set(ApiClient::class, [
    'apiKey' => $isProd ? 'secret-prod-key' : 'secret-dev-key',
    'apiUrl' => 'https://api.example.com'
]);

$apiClient = $container->get(ApiClient::class); // Uses parameters above
```

### 4. Type Interceptors

```php
class MyService {
    public bool $flagged = false;
}

class MyInterceptor implements TypeInterceptorInterface {
    public static function supports(object|string $target): bool {
        return $target === MyService::class;
    }
    public function intercept(object $instance): void {
        $instance->flagged = true;
    }
}

$container->registerTypeInterceptor(MyInterceptor::class);
$container->get(MyService::class)->flagged; // true
```

### 5. Tags

```php
$container->singleton(FileLogger::class);
$container->singleton(DatabaseLogger::class);

$container->tag(FileLogger::class, ['loggers', 'file']);
$container->tag(DatabaseLogger::class, ['loggers', 'db']);

$loggers = $container->getTagged('loggers');

foreach ($loggers as $logger) {
    $logger->log('Hello from tagged logger!');
}
```

### 6. Compiling the Container

```php
$file = __DIR__ . '/CompiledContainer.php';

if (file_exists($file) && !$_ENV['DEV']) {
    require_once $file;
    $container = new CompiledContainer();
} else {
    $container = new ServiceContainer();
    // configure $container...

    $compiler = new ContainerCompiler($container);
    $compiler->compileToFile($file);
}
```

---

## License

MIT License

