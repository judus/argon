[![PHP](https://img.shields.io/badge/php-8.2+-blue)](https://www.php.net/)
[![Build](https://github.com/judus/argon/actions/workflows/php.yml/badge.svg)](https://github.com/judus/argon/actions)
[![codecov](https://codecov.io/gh/judus/argon/branch/master/graph/badge.svg)](https://codecov.io/gh/judus/argon)
[![Psalm Level](https://shepherd.dev/github/judus/argon/coverage.svg)](https://shepherd.dev/github/judus/argon)
[![Code Style](https://img.shields.io/badge/code%20style-PSR--12-brightgreen.svg)](https://www.php-fig.org/psr/psr-12/)
[![Latest Version](https://img.shields.io/packagist/v/maduser/argon.svg)](https://packagist.org/packages/maduser/argon)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)

# Argon Service Container

A high-performance, PSR-11 compliant dependency injection container with optional compilation.

_**Strict when you want it, magic when you allow it**_

Argon compiles your service graph into native PHP code, eliminating reflection and runtime resolution overhead. 
When no binding exists, it seamlessly falls back to autowiring constructors, closures, and methods — offering predictable, optimized performance when declared, and ~~black magic~~ convenient flexibility when not.

- **Adaptable**: strict or dynamic, compiled or runtime — it's up to you.
- **Framework-agnostic**: no vendor lock-in, no framework dependencies.
- **Optimized for production**: compiled output is pure PHP, ready for opcode caching.
- **Feature-rich**: lifecycle hooks, contextual bindings, decorators, and more.
- **Predictable**: clear and consistent API, no annotations, no attributes, no YAML. Just PHP.

### Strict vs. Magic

Every `ArgonContainer` instance can be created in **strict mode**:

```php
$container = new ArgonContainer(strictMode: true);
```

- **Strict mode** only resolves services you have explicitly registered. Requests for unbound classes throw `NotFoundException` (both at runtime and in compiled containers).
- **Magic mode** (default) still prefers explicit bindings, but will autowire instantiable classes, invoke closures, and fall back to the dynamic resolver when compiled code doesn’t have a pre-generated method.

When you compile the container, strict mode is baked into the generated class:

```php
$compiler->compile($file, 'ProdContainer', namespace: 'App\\Compiled', strictMode: true);
```

or simply let the compiler mirror the runtime flag if you instantiate with `strictMode: true`.

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

### Binding and Resolving Services

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
### Binding Arguments

When registering a service, you can provide **constructor arguments** using an associative array.  
These arguments are matched by **name** to the constructor’s parameter list — no need for full signatures or complex configuration.

```php
class ApiClient
{
    public function __construct(string $apiKey, string $apiUrl) {}
}
```

#### Set arguments during binding

```php
$container->set(ApiClient::class, args: [
    'apiKey' => 'dev-123',
    'apiUrl' => 'https://api.example.com',
]);
```

These arguments are attached to the service binding and used **every time** it's resolved.

#### Override arguments during resolution (transients only)

```php
$client = $container->get(ApiClient::class, args: [
    'apiKey' => 'prod-999',
    'apiUrl' => 'https://api.example.com',
]);
```

This works only for **transient** services. Shared services are constructed once, and cannot be reconfigured at runtime.

### Automatic Dependency Resolution

```php
class Logger {}

class UserService
{
    public function __construct(Logger $logger, string $env = 'prod') {}
}

$container->get(UserService::class); // Works out of the box

$strict = new ArgonContainer(strictMode: true);
$strict->set(Logger::class);
$strict->get(UserService::class); // OK

$strict->get(Logger::class); // ✅ registered binding
```
Argon will resolve `Logger` by class name, and skip `env` because it's optional. In **strict mode**, autowiring only works when you bind the dependency (as shown above); otherwise a `NotFoundException` is thrown.

What will **NOT** work in either mode:
```php
interface LoggerInterface {}

class UserService
{
    public function __construct(LoggerInterface $logger, string $env) {}
}

$container->get(UserService::class); // 500: No interface binding, no default value for $env.
```
In this case, you must bind the interface to a concrete class first and provide a default value for the primitive:
```php
$container->set(LoggerInterface::class, FileLogger::class);
$container->set(UserService::class, args: [
    'env' => $_ENV['APP_ENV'],
]);
```


### Parameter Registry

The **parameter registry** is a built-in key/value store used to centralize application configuration. It is fully 
compatible with the **compiled container** — values are embedded directly into the generated service code.

Use it to define reusable values, inject environment settings.

#### Set and retrieve values

```php
$parameters = $container->getParameters();

$parameters->set('apiKey', $_ENV['APP_ENV'] === 'prod' ? 'prod-key' : 'dev-key');
$parameters->set('apiUrl', 'https://api.example.com');

$apiKey = $parameters->get('apiKey');
```

#### Use parameters in bindings or at resolution

```php
$container->set(ApiClient::class, args: [
    'apiKey' => $parameters->get('apiKey'),
    'apiUrl' => $parameters->get('apiUrl'),
]);
```
TIP: you can wrap the parameter registry with your own "ConfigRepository" and implement validation, scopes via dot notation, etc.


### Factory Bindings

Use `factory()` to bind a service to a dedicated factory class.  
The factory itself is resolved via the container and may define either an `__invoke()` method or a named method.

```php
$container->set(ClockInterface::class)
    ->factory(ClockFactory::class);
```

This resolves and calls `ClockFactory::__invoke()`.

To use a specific method:

```php
$container->set(ClockInterface::class)
    ->factory(ClockFactory::class, 'create');
```

### Contextual Bindings

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

### Service Providers

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

### Interceptors

Interceptors allow you to hook into the service resolution lifecycle. They are automatically called either **before** or **after** a service is constructed.

#### Post-Resolution Interceptors

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

#### Pre-Resolution Interceptors

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

### Extending Services

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


### Tags

```php
$container->tag(FileLogger::class, ['loggers', 'file']);
$container->tag(DatabaseLogger::class, ['loggers', 'db']);

/** @var iterable<LoggerInterface> $loggers */
$loggers = $container->getTagged('loggers');

foreach ($loggers as $logger) {
    $logger->log('Hello from tagged logger!');
}
```

### Conditional Service Access

`optional()` returns a proxy if the service is unavailable — safe for optional dependencies.

```php
// Suppose SomeLogger is optional
$container->optional(SomeLogger::class)->log('Only if logger exists');

// This won't throw, even if SomeLogger wasn't registered
```

### Closure Bindings with Autowired Parameters

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

### Compiling the Container

```php
$file = __DIR__ . '/CompiledContainer.php';

if (file_exists($file) && !$_ENV['DEV']) {
    require_once $file;
    $container = new App\Compiled\ProdContainer();
} else {
    $container = new ArgonContainer(strictMode: true);
    // configure $container...

    $compiler = new ContainerCompiler($container);
    $compiler->compile(
        $file,
        className: 'ProdContainer',
        namespace: 'App\\Compiled',
        strictMode: true
    );
}
```
The compiled container is a pure PHP class with zero runtime resolution logic for standard bindings. In **strict mode** the generated class omits the dynamic fallback entirely—missing registrations fail fast with `NotFoundException`. In magic mode it continues to fall back to the runtime resolver when needed.


---

## `ArgonContainer` API

| ArgonContainer            | Parameters                                      | Return                                     | Description                                                                       |
|---------------------------|-------------------------------------------------|--------------------------------------------|-----------------------------------------------------------------------------------|
| `set()`                   | `string $id`, `Closure\|string\|null $concrete` | `ArgonContainer`                           | Registers a service as shared by default (use `->transient()` to override)        |
| `get()`                   | `string $id`                                    | `object`                                   | Resolves and returns the service.                                                 |
| `has()`                   | `string $id`                                    | `bool`                                     | Checks if a service binding exists.                                               |
| `getBindings()`           | –                                               | `array<string, ServiceDescriptor>`         | Returns all registered service descriptors.                                       |
| `getContextualBindings()` | –                                               | `ContextualBindingsInterface`              | Returns all contextual service descriptors.                                       |
| `getDescriptor()`         | `string $id`                                    | `ServiceDescriptorInterface`               | Returns the service description associated with the id.                           |
| `getParameters()`         | –                                               | `ParameterStoreInterface`                  | Access the parameter registry for raw or shared values.                           |
| `registerInterceptor()`   | `class-string<InterceptorInterface> $class`     | `ArgonContainer`                           | Registers a type interceptor.                                                     |
| `registerProvider()`      | `class-string<ServiceProviderInterface> $class` | `ArgonContainer`                           | Registers and invokes a service provider.                                         |
| `tag()`                   | `string $id`, `list<string> $tags`              | `ArgonContainer`                           | Tags a service with one or more labels.                                           |
| `getTags()`               | –                                               | `array<string, list<string>>`              | Returns all tag definitions in the container.                                     |
| `getTagged()`             | `string $tag`                                   | `list<object>`                             | Resolves all services tagged with the given label.                                |
| `boot()`                  | –                                               | `ArgonContainer`                           | Bootstraps all registered service providers.                                      |
| `extend()`                | `string $id`  `callable $decorator`             | `ArgonContainer`                           | Decorates an already-resolved service at runtime.                                 |
| `for()`                   | `string $target`                                | `ContextualBindingBuilder`                 | Begins a contextual binding chain — call `->set()` to define per-target bindings. |
| `getPreInterceptors()`    | –                                               | `list<class-string<InterceptorInterface>>` | Lists all registered pre-interceptors.                                            |
| `getPostInterceptors()`   | –                                               | `list<class-string<InterceptorInterface>>` | Lists all registered post-interceptors.                                           |
| `invoke()`                | `callable $target`, `array $params = []`        | `mixed`                                    | Calls a method or closure with injected dependencies.                             |
| `isResolvable()`          | `string $id`                                    | `bool`                                     | Checks if a service can be resolved, even if not explicitly bound.                |
| `optional()`              | `string $id`                                    | `object`                                   | Resolves a service or returns a NullServiceProxy if not found.                    |
| `isStrictMode()`          | –                                               | `bool`                                     | Indicates whether the container was instantiated in strict mode.                 |

## `BindingBuilder` API

When you call `set()`, it returns a `BindingBuilder`, which lets you **configure** the binding fluently.

| Method               | Parameters                                       | Return                       | Description                                                                              |
|----------------------|--------------------------------------------------|------------------------------|------------------------------------------------------------------------------------------|
| `transient()`        | –                                                | `BindingBuilder`             | Marks the service as non-shared. A new instance will be created for each request.        |
| `skipCompilation()`  | –                                                | `BindingBuilder`             | Excludes this binding from the compiled container. Useful for closures or dynamic logic. |
| `tag()`              | `string\|list<string> $tags`                     | `BindingBuilder`             | Assigns one or more tags to this service.                                                |
| `factory()`          | `string $factoryClass`, `?string $method = null` | `BindingBuilder`             | Uses a factory class (optionally a method) to construct the service.                     |
| `defineInvocation()` | `string $methodName`, `array $args = []`         | `BindingBuilder`             | Pre-defines arguments for a later `invoke()` call. Avoids reflection at runtime.         |
| `getDescriptor()`    | –                                                | `ServiceDescriptorInterface` | Returns the internal service descriptor for advanced inspection or modification.         |

---


## License

MIT License
<!--
Argon is free and open-source. If you use it commercially or benefit from it in your work, please consider sponsoring or contributing back to support continued development.
-->
