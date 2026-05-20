# Changelog

All notable changes to `maduser/argon` will be documented in this file.

## Unreleased

### Changed

- Runtime arguments for shared services are now accepted only before the shared instance is created. Passing runtime arguments after a shared service has already been resolved throws `ContainerException` instead of silently ignoring them.
- `composer check` is now a non-mutating quality gate. Code style fixes remain available through `composer phpcs:fix`.
- `composer test:coverage` no longer opens the generated coverage report as a GUI side effect.

### Fixed

- Compiled containers now honor transient lifecycle for regular and factory bindings.
- Compiled shared services now apply post-resolution interceptors only when the shared instance is created.
- Closure bindings now resolve parameters through the container at runtime.
- Contextual closure bindings now resolve parameters through the container at runtime.
- Compiled containers now throw `ContainerException` for missing required runtime arguments instead of leaking undefined-array-key notices.
- Compiled argument resolution now preserves explicit `null` arguments instead of treating them as absent.
- `isResolvable()` now respects strict mode and only treats concrete instantiable classes as implicitly resolvable.
- PHP 8.4 test deprecations for implicit nullable parameters were removed.

### Documentation

- Clarified that `optional()` is intentionally binding-based and does not autowire unbound concrete classes.
- Clarified runtime argument behavior for shared services and transient services.
