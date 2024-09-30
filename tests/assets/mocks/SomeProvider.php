<?php

namespace Tests\Mocks;

use Maduser\Argon\Container\ServiceProvider;
use Psr\Log\LoggerInterface;

class SomeProvider extends ServiceProvider
{
    public function register(): void
    {

        $this->container->set('SomeObject', function (LoggerInterface $logger) {
            return new SomeService($logger);
        });

        $this->container->set('AnotherObject', function (SomeService $someService) {
            return $someService;
        });
    }

    public function resolve(): mixed
    {
        return [
            'SomeObject' => $this->container->get('SomeObject'),
            'AnotherObject' => $this->container->get('AnotherObject')
        ];
    }
}