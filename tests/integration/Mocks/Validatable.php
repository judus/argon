<?php

declare(strict_types=1);

namespace Tests\Integration\Mocks;

interface Validatable
{
    public function validate(): void;
}
