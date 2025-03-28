<?php

declare(strict_types=1);

namespace Tests\Integration\Mocks;

final class NullableApiClient
{
    public function __construct(
        public ?string $apiKey,
        public ?string $apiUrl
    ) {
    }
}
