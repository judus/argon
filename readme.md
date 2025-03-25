<!--
[![PHP Version](https://img.shields.io/badge/php-8.2+-blue.svg)](https://www.php.net/)
[![License](https://img.shields.io/github/license/maduser/argon)](LICENSE)
[![Tests](https://img.shields.io/badge/tests-passing-brightgreen)](./tests)
[![Coverage](https://img.shields.io/badge/coverage-100%25-success)](#)
[![Build](https://img.shields.io/github/actions/workflow/status/maduser/argon/ci.yml?branch=main)](https://github.com/maduser/argon/actions)
[![Latest Version](https://img.shields.io/packagist/v/maduser/argon.svg)](https://packagist.org/packages/maduser/argon)
-->

# Argon Service Container

A high-performance, PSR-11 compliant dependency injection container for modern PHP applications.

Argon focuses on ease of use without compromising features, performance, or flexibility. It combines intuitive service binding with blazing-fast compiled resolution, and supports powerful service lifecycle extensions via customizable type interceptors.

---

## Features

- **🔥 Compilable**: Eliminate runtime reflection entirely with precompiled service definitions.
- **⚙️ PSR-11 Compliant**: Drop-in compatibility with standard PSR-11 containers.
- **🧠 Autowiring**: Automatically resolve dependencies using constructor signatures.
- **♻️ Singleton & Transient Services**: Use shared or separate instances per request.
- **🧩 Parameter Overrides**: Inject primitives and custom values into your services.
- **🔁 Contextual Bindings**: Different dependencies per consumer class.
- **🧰 Service Providers**: Group and encapsulate service registrations.
- **🛠 Type Interceptors**: Add post-resolution behavior to specific services (e.g., validation, tagging, initialization).
- **❓ Conditional Resolution**: Safely access optional services using `if()`.
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

### Philosophy: Why Argon?

The Argon Service Container provides a human-friendly API and compiles your service graph into native PHP code. There’s no reflection overhead, no service guessing, and no performance surprises. It favors **a single, consistent way** of doing things — no YAML vs. XML debates, no annotation magic, no framework coupling. Just clear, explicit, testable PHP.

Argon gives you modern, enterprise-grade dependency injection with the simplicity and control of raw PHP.

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

class UserService
{
    public function __construct(Logger $logger) {}
}

$container->get(UserService::class); // Works out of the box
```

### 3. Parameter Overrides
Parameter overrides allow you to inject primitive values or custom arguments into service constructors. These values are matched by parameter name.
```php
class ApiClient
{
    public function __construct(string $apiKey, string $apiUrl) {}
}

$container->getParameters()->set(ApiClient::class, [
    'apiKey' => $_ENV['APP_ENV'] === 'prod' ? 'prod-key' : 'dev-key',
    'apiUrl' => 'https://api.example.com'
]);

$apiClient = $container->get(ApiClient::class);
```

### 4. Contextual Bindings

Contextual bindings allow different consumers to receive different implementations of the same interface.

```php
interface LoggerInterface {}
class FileLogger implements LoggerInterface {}
class DatabaseLogger implements LoggerInterface {}

class ServiceA 
{
    public function __construct(LoggerInterface $logger) {}
}

class ServiceB 
{
    public function __construct(LoggerInterface $logger) {}
}

$container->for(ServiceA::class)
    ->set(LoggerInterface::class, DatabaseLogger::class);

// Same Interface, different implementation
$container->for(ServiceB::class)
    ->set(LoggerInterface::class, FileLogger::class);
```

### 5. Service Providers

Service providers allow grouping service bindings and optional boot-time logic.

```php
class AppServiceProvider implements ServiceProviderInterface
{
    // called before compilation and should be used to declare bindings
    public function register(ServiceContainer $container): void 
    {
        $container->singleton(LoggerInterface::class, FileLogger::class);
        $container->bind(CacheInterface::class, RedisCache::class);
    }
    
    // Executed after compilation, once the container is ready to resolve services
    public function boot(ServiceContainer $container): void 
    {
        // Optional setup logic
    }
}

$container->registerServiceProvider(AppServiceProvider::class);
$container->bootServiceProviders();
```

### 6. Type Interceptors

Type interceptors allow you to hook into service resolution to apply post-construction logic. 

```php
interface Validatable
{
    public function validate(): void;
}

class MyDTO implements Validatable
{
    public function validate(): void
    {
        // Verify required state after construction
    }
}

class ValidationInterceptor implements TypeInterceptorInterface
{
    public static function supports(object|string $target): bool
    {
        return is_subclass_of($target, Validatable::class);
    }

    public function intercept(object $instance): void
    {
        $instance->validate();
    }
}

// Register the interceptor
$container->registerInterceptor(ValidationInterceptor::class);

// validate() is automatically called after resolution
$dto = $container->get(MyDTO::class);
```

### 7. Tags

```php
$container->tag(FileLogger::class, ['loggers', 'file']);
$container->tag(DatabaseLogger::class, ['loggers', 'db']);

/** @var iterable<LoggerInterface> $loggers */
$loggers = $container->getTagged('loggers');

foreach ($loggers as $logger) {
    $logger->log('Hello from tagged logger!');
}
```

### 8. Conditional Service Acces

`if()` returns a proxy if the service is unavailable — safe for optional dependencies.

```php
// Suppose SomeLogger is optional
$container->if(SomeLogger::class)->log('Only if logger exists');

// This won't throw, even if SomeLogger wasn't registered
```

### 9. Closure Bindings with Autowired Parameters

Closure bindings are convenient for CLI scripts, testing, or quick one-off tools, but generally not suited for production service graphs. They are not included in the compiled container and must be registered at runtime:

```php
// In a ServiceProvider 
public function boot(ServiceContainer $container): void
{
    $container->singleton(LoggerInterface::class, fn (Config $config) => {
        return new FileLogger($config->get('log.path'));
    });
}
```

### 10. Compiling the Container

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
The compiled container is a pure PHP class with zero runtime resolution logic for standard bindings. It eliminates reflections and parameter lookups by generating dedicated methods for each service. All bindings, tags, parameters, and interceptors are statically resolved and written as native PHP code — ready to be opcode-cached and preloaded in production.

No config parsing. No service resolution logic. No performance bottlenecks.

Just raw, optimized, dependency injection at runtime speed.

---

## License

MIT License
<!--
Argon is free and open-source. If you use it commercially or benefit from it in your work, please consider sponsoring or contributing back to support continued development.
-->
