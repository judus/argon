<?php

declare(strict_types=1);

namespace Tests\Integration\Mocks;

final class InterceptedClass implements Validatable
{
    public bool $validated = false;
    public bool $post1 = false;
    public bool $post2 = false;
    public string $value = '';

    public function __construct(string $value = '')
    {
        $this->value = $value;
    }

    public function validate(): void
    {
        $this->validated = true;
    }
}
