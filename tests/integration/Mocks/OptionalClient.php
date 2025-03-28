<?php

declare(strict_types=1);

namespace Tests\Integration\Mocks;

final class OptionalClient
{
    public function __construct(
        public string $apiKey = 'default-key',
        public string $apiUrl = 'https://default.com'
    ) {
    }
}
