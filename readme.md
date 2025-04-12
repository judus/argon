[![PHP](https://img.shields.io/badge/php-8.2+-blue)](https://www.php.net/)
[![Build](https://github.com/judus/argon/actions/workflows/php.yml/badge.svg)](https://github.com/judus/argon/actions)
[![codecov](https://codecov.io/gh/judus/argon/branch/master/graph/badge.svg)](https://codecov.io/gh/judus/argon)
[![Psalm Level](https://shepherd.dev/github/judus/argon/coverage.svg)](https://shepherd.dev/github/judus/argon)
[![Code Style](https://img.shields.io/badge/code%20style-PSR--12-brightgreen.svg)](https://www.php-fig.org/psr/psr-12/)
[![Latest Version](https://img.shields.io/packagist/v/maduser/argon.svg)](https://packagist.org/packages/maduser/argon)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)

# Argon Service Container

A compilable, PSR-11 compliant dependency injection container.

Argon compiles your service graph into a native PHP class — no reflection, no guesswork, just fast, explicit wiring. Prefer autowiring? Argon handles that too, with smart, reliable resolution for services, methods, and closures.

It favors a single, unambiguous way to define services: plain PHP.
No YAML. No annotations. Just testable code.

And definitely no attributes: #[DoSomethingElse].

---

## Features

- **🔥 Compilable**: Eliminate runtime reflection with native, precompiled service classes.
- **⚙️ PSR-11 Compliant**: Drop-in compatibility with standard containers.
- **🧠 Autowiring**: Automatically resolves constructor, method, and closure dependencies.
- **♻️ Singleton & Transient Services**: Use shared or separate instances per resolution.
- **🧩 Parameter Overrides**: Inject primitives and scalar values by name or context.
- **🔁 Contextual Bindings**: Provide different implementations depending on the consumer.
- **🧰 Service Providers**: Group related bindings and lifecycle logic into reusable classes.
- **🛠 Interceptors**: Hook into service creation before or after instantiation.
- **🛠 Factories**: Register custom factory methods for fine-tuned instantiation.
- **🎭 Decorators**: Extend or wrap existing services transparently at runtime.
- **🧱 Runtime Extension**: Modify bindings, decorate services, or register interceptors on the fly.
- **❓ Optional Resolution**: Use `optional()` for soft dependency injection with graceful fallbacks.
- **⏱ Lazy Loading**: Services are only created when actually needed.
- **🚨 Circular Dependency Detection**: Protects against infinite loops in your graph.

---

Want me to move on to the next section and give the same treatment to **Installation** and **Usage**?
---

## Installation

```bash
$ composer require maduser/argon
```

Requires PHP 8.2+

### Tests & QA

```bash
$ composer install
$ vendor/bin/phpunit
$ vendor/bin/psalm
$ vendor/bin/phpcs

# or all checks combined
$ composer check
```

---

## Usage

### 1. Binding and Resolving Services

```php
// Register shared services (default)
$container->set(MyService::class);
$container->set(MyService::class, MyService::class); // explicit form

// Register transient (non-shared) services
$container->set(MyOtherService::class)->transient();
$container->set(LoggerInterface::class, FileLogger::class)->transient();

// Register interface to concrete binding
$container->set(CacheInterface::class, InMemoryCache::class);

// Resolve services
$shared = $container->get(MyService::class);
$transient = $container->get(MyOtherService::class);
$cache = $container->get(CacheInterface::class);
$logger = $container->get(LoggerInterface::class);
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

### 3. Constructor Arguments & Parameter Registry

You can inject primitive values or custom arguments into service constructors by matching argument names. Arguments can be either raw values or retrieved from the parameter registry. They may be applied when binding a service or passed at resolution time (for transient services only).

```php
class ApiClient
{
    public function __construct(string $apiKey, string $apiUrl) {}
}
```

#### 🔹 Bind custom arguments to a service

```php
$container->set(ApiClient::class, args: [
    'apiKey' => $_ENV['APP_ENV'] === 'prod' ? 'prod-key' : 'dev-key',
    'apiUrl' => 'https://api.example.com'
]);

$container->get(ApiClient::class);
```

These arguments will be applied every time this binding is resolved.


#### 🔹 Resolve a service with custom arguments (transients only)

```php
$container->get(ApiClient::class, args: [
    'apiKey' => $_ENV['APP_ENV'] === 'prod' ? 'prod-key' : 'dev-key',
    'apiUrl' => 'https://api.example.com'
]);
```

These arguments are used only for this specific call. They will not affect singleton instances.


#### 🔹 Store parameters in the parameter registry

```php
$parameters = $container->getParameters();

$parameters->set('apiUrl', 'https://api.example.com');
$parameters->set('apiKey', $_ENV['APP_ENV'] === 'prod' ? 'prod-key' : 'dev-key');
```


#### 🔹 Bind arguments using values from the registry

```php
$container->set(ApiClient::class, args: [
    'apiKey' => $parameters->get('apiKey'),
    'apiUrl' => $parameters->get('apiUrl')
]);

$container->get(ApiClient::class);
```


#### 🔹 Resolve a service with arguments from the registry (transients only)

```php
$container->get(ApiClient::class, args: [
    'apiKey' => $parameters->get('apiKey'),
    'apiUrl' => $parameters->get('apiUrl')
]);
```

These arguments will only apply to this specific resolution.



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
    public function register(ArgonContainer $container): void 
    {
        $container->set(LoggerInterface::class, FileLogger::class);
        $container->set(CacheInterface::class, RedisCache::class)->transient();
    }
    
    // Executed after compilation, once the container is ready to resolve services
    public function boot(ArgonContainer $container): void 
    {
        // Optional setup logic
    }
}

$container->register(AppServiceProvider::class);
$container->boot();
```

### 6. Interceptors

Interceptors allow you to hook into the service resolution lifecycle. They are automatically called either **before** or **after** a service is constructed.

#### 🔹 Post-Resolution Interceptors

These are executed **after** a service is created, and can modify the object (e.g., inject metadata, call validation, register hooks).

```php
use Maduser\Argon\Container\Contracts\PostResolutionInterceptorInterface;

interface Validatable
{
    public function validate(): void;
}

class MyDTO implements Validatable
{
    public function validate(): void
    {
        // Verify required state
    }
}

class ValidationInterceptor implements PostResolutionInterceptorInterface
{
    public static function supports(string $id): bool
    {
        return is_subclass_of($id, Validatable::class);
    }

    public function intercept(object $instance): void
    {
        $instance->validate();
    }
}

$container->registerInterceptor(ValidationInterceptor::class);
$dto = $container->get(MyDTO::class); // validate() is automatically called
```

#### 🔹 Pre-Resolution Interceptors

These run **before** a service is instantiated. They can modify constructor parameters or short-circuit the entire resolution.

```php
use Maduser\Argon\Container\Contracts\PreResolutionInterceptorInterface;

class EnvOverrideInterceptor implements PreResolutionInterceptorInterface
{
    public static function supports(string $id): bool
    {
        return $id === ApiClient::class;
    }

    public function intercept(string $id, array &$parameters): ?object
    {
        $parameters['apiKey'] = $_ENV['APP_ENV'] === 'prod'
            ? 'prod-key'
            : 'dev-key';

        return null; // let container continue
    }
}

class StubInterceptor implements PreResolutionInterceptorInterface
{
    public static function supports(string $id): bool
    {
        return $id === SomeHeavyService::class && $_ENV['TESTING'];
    }

    public function intercept(string $id, array &$parameters): ?object
    {
        return new FakeService(); // short-circuit
    }
}

$container->registerInterceptor(EnvOverrideInterceptor::class);
$container->registerInterceptor(StubInterceptor::class);
```

- Interceptors must implement either `PreResolutionInterceptorInterface` or `PostResolutionInterceptorInterface`
- Both require a static `supports(string $id): bool` method to prevent unnecessary instantiation
- Interceptors are resolved lazily and only when matched
- You can register as many interceptors as you want. They're evaluated in the order they were added.

### 7. Extending Services

Extends an already-resolved service instance during runtime. Useful for wrapping, decorating, or modifying an existing service after resolution.

```php
// For example in a ServiceProvider 
public function boot(ArgonContainer $container): void
{
    $container->extend(LoggerInterface::class, function (object $logger): object {
        return new BufferingLogger($logger);
    });
}
```

From this point on, all calls to `get(LoggerInterface::class)` will return the wrapped instance.


### 8. Tags

```php
$container->tag(FileLogger::class, ['loggers', 'file']);
$container->tag(DatabaseLogger::class, ['loggers', 'db']);

/** @var iterable<LoggerInterface> $loggers */
$loggers = $container->getTagged('loggers');

foreach ($loggers as $logger) {
    $logger->log('Hello from tagged logger!');
}
```

### 9. Conditional Service Access

`optional()` returns a proxy if the service is unavailable — safe for optional dependencies.

```php
// Suppose SomeLogger is optional
$container->optional(SomeLogger::class)->log('Only if logger exists');

// This won't throw, even if SomeLogger wasn't registered
```

### 10. Closure Bindings with Autowired Parameters

Closure bindings are convenient for CLI tools, prototyping, or runtime-only services — but they're not suited for production graphs or compilation. Since closures can't be compiled, you must either:
- Register them during the boot() phase of a ServiceProvider, after compilation
- Or explicitly mark them as excluded from compilation via skipCompilation()
```php
// In a ServiceProvider — boot() runs at runtime, safe for closures
public function boot(ArgonContainer $container): void
{
    $container->set(LoggerInterface::class, fn (Config $config) => {
        return new FileLogger($config->get('log.path'));
    });
}
```
```php
// Exclude from compilation explicitly
$container->set(LoggerInterface::class, fn (Config $config) => {
    return new FileLogger($config->get('log.path'));
})->skipCompilation();
```

### 11. Compiling the Container

```php
$file = __DIR__ . '/CompiledContainer.php';

if (file_exists($file) && !$_ENV['DEV']) {
    require_once $file;
    $container = new CompiledContainer();
} else {
    $container = new ArgonContainer();
    // configure $container...

    $compiler = new ContainerCompiler($container);
    $compiler->compileToFile($file);
}
```
The compiled container is a pure PHP class with zero runtime resolution logic for standard bindings. It eliminates reflections and parameter lookups by generating dedicated methods for each service. All bindings, tags, parameters, and interceptors are statically resolved and written as native PHP code — ready to be opcode-cached and preloaded in production.

No config parsing. No service resolution logic. No performance bottlenecks.

Just raw, optimized, dependency injection at runtime speed.

---

## 🧩 API

| ArgonContainer            | Parameters                                      | Return                                     | Description                                                              |
|---------------------------|-------------------------------------------------|--------------------------------------------|--------------------------------------------------------------------------|
| `set()`                   | `string $id`, `Closure\|string\|null $concrete` | `ArgonContainer`                           | Registers a service as shared by default (use `transient()` to override) |
| `get()`                   | `string $id`                                    | `object`                                   | Resolves and returns the service.                                        |
| `has()`                   | `string $id`                                    | `bool`                                     | Checks if a service binding exists.                                      |
| `getBindings()`           | –                                               | `array<string, ServiceDescriptor>`         | Returns all registered service descriptors.                              |
| `getContextualBindings()` | –                                               | `ContextualBindingsInterface`              | Returns all contextual service descriptors.                              |
| `getDescriptor()`         | `string $id`                                    | `ServiceDescriptorInterface                | null`                                                                    | Returns the descriptor for an existing binding.                          |
| `getParameters()`         | –                                               | `ParameterStoreInterface`                  | Access the parameter registry for raw or shared values.                  |
| `registerInterceptor()`   | `class-string<InterceptorInterface> $class`     | `ArgonContainer`                           | Registers a type interceptor.                                            |
| `registerProvider()`      | `class-string<ServiceProviderInterface> $class` | `ArgonContainer`                           | Registers and invokes a service provider.                                |
| `tag()`                   | `string $id`, `list<string> $tags`              | `ArgonContainer`                           | Tags a service with one or more labels.                                  |
| `getTags()`               | –                                               | `array<string, list<string>>`              | Returns all tag definitions in the container.                            |
| `getTagged()`             | `string $tag`                                   | `list<object>`                             | Resolves all services tagged with the given label.                       |
| `boot()`                  | –                                               | `ArgonContainer`                           | Bootstraps all registered service providers.                             |
| `extend()`                | `string $id`  `callable $decorator`             | `ArgonContainer`                           | Decorates an already-resolved service at runtime.                        |
| `for()`                   | `string $target`                                | `ContextualBindingBuilder`                 | Starts a contextual binding chain for a specific class.                  |
| `getPreInterceptors()`    | –                                               | `list<class-string<InterceptorInterface>>` | Lists all registered pre-interceptors.                                   |
| `getPostInterceptors()`   | –                                               | `list<class-string<InterceptorInterface>>` | Lists all registered post-interceptors.                                  |
| `invoke()`                | `object                                         | string                                     | callable                                                                 |array $target`, `array $params = []`      | `mixed`                  | Calls a method or closure with injected dependencies.           |
| `isResolvable()`          | `string $id`                                    | `bool`                                     | Checks if a service can be resolved, even if not explicitly bound.       |
| `optional()`              | `string $id`                                    | `object`                                   | Resolves a service or returns a NullServiceProxy if not found.           |

---

## License

MIT License
<!--
Argon is free and open-source. If you use it commercially or benefit from it in your work, please consider sponsoring or contributing back to support continued development.
-->
