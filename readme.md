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

Argon focuses on ease of use without compromising features, performance, or flexibility. It combines intuitive service binding with blazing-fast compiled resolution, and supports powerful service lifecycle extensions via type interceptors.

---

## Features

- **🔥 Compilable**: Eliminate runtime reflection entirely with precompiled service definitions.
- **⚙️ PSR-11 Compliant**: Drop-in compatibility with standard PSR-11 containers.
- **🧠 Autowiring**: Automatically resolve dependencies using constructor signatures.
- **♻️ Singleton & Transient Services**: Use shared or separate instances per request.
- **🧩 Parameter Overrides**: Inject primitives and custom values into your services.
- **🔁 Contextual Bindings**: Different dependencies per consumer class.
- **🧰 Service Providers**: Group and encapsulate service registrations.
- **🛠 Type Interceptors**: Apply behavior-modifying logic when services are resolved.
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
    'apiKey' => $isProd ? 'prod-key' : 'dev-key',
    'apiUrl' => 'https://api.example.com'
]);

$apiClient = $container->get(ApiClient::class); // Uses parameters above
```

### 4. Contextual Bindings

```php
interface LoggerInterface {}
class FileLogger implements LoggerInterface {}
class DatabaseLogger implements LoggerInterface {}

class ServiceA {
    public function __construct(LoggerInterface $logger) {}
}

class ServiceB {
    public function __construct(LoggerInterface $logger) {}
}

$container->for(ServiceA::class)
    ->set(LoggerInterface::class, DatabaseLogger::class);

$container->for(ServiceB::class)
    ->set(LoggerInterface::class, FileLogger::class);
```

### 5. Service Providers

Service providers are a convenient way to group related service bindings:

```php
class AppServiceProvider implements ServiceProviderInterface {
    public function register(ServiceContainer $container): void {
        $container->singleton(LoggerInterface::class, FileLogger::class);
        $container->bind(CacheInterface::class, RedisCache::class);
    }

    public function boot(ServiceContainer $container): void {
        // Optional setup logic
    }
}

$container->registerServiceProvider(AppServiceProvider::class);
$container->bootServiceProviders();
```

### 6. Type Interceptors

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

$container->registerInterceptor(MyInterceptor::class);
$container->get(MyService::class)->flagged; // true
```

### 7. Tags

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

### 8. Conditional Service Access (`if()`)

```php
// Suppose SomeLogger is optional
$container->if(SomeLogger::class)->log('Only if logger exists');

// This won't throw, even if SomeLogger wasn't registered
```

### 9. Compiling the Container

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

### 10. Binding Closures

Yes, you can bind closures — parameters will be autowired just like constructors.  
But before you do that, consider this:

1. **Closures are not compiled.**
2. **They're not best practice.**
3. **They're probably not even necessary.**

If you *must* use closures in a compiled container:

👉 **Register them at runtime in the `boot()` method of a service provider.**  

Or... just don’t compile the container at all. Unless you’re building a monster enterprise app, you won’t notice the performance hit.

---

```php
// ❌ Bad: Closure for a simple service — just use the parameter registry instead
$container->singleton(LoggerInterface::class, fn() => new FileLogger('/tmp/log.txt'));

// ❌ Also bad: Use the parameter registry unless config changes per-request
$container->singleton(LoggerInterface::class, function (DatabaseConfig $config) {
    return new DatabaseLogger($config->getConnection());
});

// ⚠️ Maybe-okay: Contextual closure for dynamic resolution
$container->for(ControllerInterface::class)
    ->set(Repository::class, function (Router $router) use ($container) {
        $alias = $router->currentRoute()->segment(1);

        if ($container->has($alias)) {
            return $container->get($alias);
        }

        throw new \Exception("No service registered for alias: {$alias}");
    });
```

---

### 🦼 Better: Use a Factory Class Instead

If you need logic, isolate it in a factory. Reusable, testable, and compiles cleanly.

```php
class RepositoryFactory
{
    public function __construct(
        private Router $router,
        private ServiceContainer $container
    ) {}

    public function __invoke(): Repository
    {
        $alias = $this->router->currentRoute()->segment(1);

        if ($this->container->has($alias)) {
            return $this->container->get($alias);
        }

        throw new \Exception("No service registered for alias: {$alias}");
    }
}

// Register the factory itself
$container->singleton(RepositoryFactory::class);

// Use it contextually — no closures needed
$container->for(ControllerInterface::class)
    ->set(Repository::class, RepositoryFactory::class);
```

_"Closures are the duct tape of DI — good for emergencies, but don’t build a spaceship with it."_


---

## License

MIT License

