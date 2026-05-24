# Argon Container

[![PHP](https://img.shields.io/badge/php-8.2+-blue)](https://www.php.net/)
[![Build](https://github.com/judus/argon-container/actions/workflows/php.yml/badge.svg)](https://github.com/judus/argon-container/actions)
[![codecov](https://codecov.io/gh/judus/argon-container/branch/master/graph/badge.svg)](https://codecov.io/gh/judus/argon-container)
[![Psalm Level](https://shepherd.dev/github/judus/argon-container/coverage.svg)](https://shepherd.dev/github/judus/argon-container)
[![Latest Version](https://img.shields.io/packagist/v/maduser/argon-container.svg)](https://packagist.org/packages/maduser/argon-container)
[![Downloads](https://img.shields.io/packagist/dt/maduser/argon-container.svg)](https://packagist.org/packages/maduser/argon-container)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)

`maduser/argon-container` is the dependency injection container at the center of the
Argon package suite. It is PSR-11 compatible, strict by design, and built around
explicit service definitions instead of framework magic.

## Installation

```bash
composer require maduser/argon-container
```

## Quick Start

```php
use Maduser\Argon\Container\ArgonContainer;

$container = new ArgonContainer();

$container->set(LoggerInterface::class, MonologLogger::class)
    ->shared();

$logger = $container->get(LoggerInterface::class);
```

Service providers can group package or application registrations:

```php
$container->register(AppServiceProvider::class);
$container->boot();
```

## Scope

The container owns service binding, argument resolution, contextual bindings,
service tags, lifecycle hooks, and compiled-container generation. It does not
provide HTTP routing, middleware, console commands, or application bootstrapping;
those belong to the surrounding Argon packages.

## Quality Gate

```bash
composer check
```
