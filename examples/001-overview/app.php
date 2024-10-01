<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use Maduser\Argon\Container\Exceptions\AuthorizationException;
use Maduser\Argon\Container\Exceptions\ValidationException;
use Maduser\Argon\Container\ServiceContainer;
use Psr\Container\ContainerExceptionInterface;
use Psr\Log\LoggerInterface;
use Tests\Mocks\AnotherService;
use Tests\Mocks\DatabaseLogger;
use Tests\Mocks\FileLogger;
use Tests\Mocks\SomeProvider;
use Tests\Mocks\SomeService;
use Tests\Mocks\ValidatableService;


$container = new ServiceContainer();
$container->bind(LoggerInterface::class, DatabaseLogger::class); // Bind LoggerInterface to DatabaseLogger

// Registering services
$container->set('logger', FileLogger::class); // Register FileLogger with alias 'logger'

// Registering a singleton service
$container->singleton('databaseLogger', function () {
    return new DatabaseLogger();
}); // Register DatabaseLogger as a singleton

$container->bind(LoggerInterface::class, DatabaseLogger::class); // Bind LoggerInterface to DatabaseLogger again

// Registering a service provider
$container->set('SomeProvidedService', SomeProvider::class);

// Resolving a service
$logger = $container->get('logger');
$logger->info("Test log to file"); // Log a message using the resolved FileLogger

// Singleton resolution
$databaseLogger = $container->get('databaseLogger');
$databaseLogger->info("Test log to database"); // Log a message using the singleton DatabaseLogger

// Testing singleton behavior
$singleton1 = $container->get('databaseLogger');
$singleton2 = $container->get('databaseLogger');

if ($singleton1 === $singleton2) {
    echo "Singleton works, same instance returned." . PHP_EOL; // Verifies that the singleton returns the same instance
} else {
    echo "Singleton failed, different instances returned." . PHP_EOL;
}

// Dependency Injection
$someService = new SomeService($container->get(LoggerInterface::class));
$someService->doSomething('Some service with injected logger'); // Verifies DI with logger

// Auto resolution
$someService2 = $container->make(SomeService::class);
$someService2->doSomething("Some service with auto resolution"); // Demonstrates automatic resolution without explicit binding

// Service provider resolution
$someProvidedServices = $container->get('SomeProvidedService');
echo sprintf("SomeProvidedService returned %s service(s): ", count($someProvidedServices)) . PHP_EOL;
var_dump($someProvidedServices); // Resolves services provided by SomeProvider and checks how many were provided

// Direct service resolution
$container->set('someService', SomeService::class);
$container->get('someService')->doSomething('Some message'); // Resolves and uses the 'someService'

// Service resolution using closure
$container->set('someOtherService', function () use ($container) {
    return new SomeService($container->get(LoggerInterface::class));
});
$container->get('someOtherService')->doSomething('Another message'); // Demonstrates service resolution using closure

// DI with closure-based service
$container->set('finalService', function (LoggerInterface $logger) {
    return new SomeService($logger);
});
$container->get('finalService')->doSomething('Final message'); // Uses DI for logger in closure-based service

// Validatable and Authorizable service example
try {
    $container->set('exampleService1', function () {
        return new ValidatableService([
            'name' => 'John Doe',
            'role' => 'admin',
        ]);
    });
    $exampleService1 = $container->get('exampleService1');
    $data = $exampleService1->getValidatedData();
    echo "Validated data: " . print_r($data, true) . PHP_EOL; // Retrieves and prints validated data
    echo "Service authorized successfully." . PHP_EOL; // Successful authorization message
} catch (ValidationException $e) {
    echo "Validation errors: " . print_r($e->getErrors(), true) . PHP_EOL;
} catch (AuthorizationException $e) {
    echo $e->getMessage() . PHP_EOL;
} catch (ContainerExceptionInterface $e) {
    echo $e->getMessage() . PHP_EOL;
}

// Testing service with failed authorization
try {
    $container->set('exampleService2', function () {
        return new ValidatableService([
            'name' => 'John Doe',
            'role' => 'guest',
        ]);
    });
    $exampleService2 = $container->get('exampleService2');
    $data = $exampleService2->getValidatedData();
    echo "Validated data: " . print_r($data, true) . PHP_EOL;
    echo "Service authorized successfully." . PHP_EOL;
} catch (ValidationException $e) {
    echo "Validation errors: " . print_r($e->getErrors(), true) . PHP_EOL;
} catch (AuthorizationException $e) {
    echo $e->getMessage() . PHP_EOL; // Demonstrates failed authorization handling
    return null; // Continue since we want to demonstrate the next example
} catch (ContainerExceptionInterface $e) {
    echo $e->getMessage() . PHP_EOL;
}

// Testing service with failed validation
try {
    $container->set('exampleService3', function () {
        return new ValidatableService([
            'name' => '',
            'role' => 'admin',
        ]);
    });
    $exampleService3 = $container->get('exampleService3');
    $data = $exampleService3->getValidatedData();
    echo "Validated data: " . print_r($data, true) . PHP_EOL;
    echo "Service authorized successfully." . PHP_EOL;
} catch (ValidationException $e) {
    echo "Validation errors: " . print_r($e->getErrors(), true) . PHP_EOL; // Demonstrates failed validation
    return null; // Continue since we want to demonstrate the next example
} catch (AuthorizationException $e) {
    echo $e->getMessage() . PHP_EOL;
} catch (ContainerExceptionInterface $e) {
    echo $e->getMessage() . PHP_EOL;
}

// Tagging services
$container->set('someService', SomeService::class);
$container->tag('someService', ['utility', 'logger']); // Tags someService with 'utility' and 'logger'

$container->set('anotherService', AnotherService::class);
$container->tag('anotherService', ['utility']); // Tags anotherService with 'utility'

$utilityServices = $container->tagged('utility');
$loggerServices = $container->tagged('logger');
echo sprintf('There are %s utility and %s logger services', count($utilityServices), count($loggerServices)) . PHP_EOL; // Shows the number of tagged services

// Conditional service execution with if()
$container->if('someService')->doSomething('Some message'); // Executes method if service exists
$container->if('foo')->doSomething('Some message'); // Does nothing (service 'foo' does not exist)


$container->set('SomeOtherService', function(LoggerInterface $logger, FileLogger $fileLogger) use ($container) {
    $fileLogger->info('Some message from fileLogger within callback');
    return new SomeService($logger);
});

$container->get('SomeOtherService')->doSomething('Some message from SomeOtherService'); // Resolves and uses the 'SomeOtherService'

