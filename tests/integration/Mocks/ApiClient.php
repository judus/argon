<?php

declare(strict_types=1);

namespace Tests\Integration\Mocks;

final class ApiClient
{
    public function __construct(
        public string $apiKey,
        public string $apiUrl
    ) {
    }
}
