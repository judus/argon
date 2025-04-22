<?php

declare(strict_types=1);

namespace Tests\Integration\Compiler\Mocks;

class DefaultValueService
{
    public string $label = 'default-label';

    public function __construct(string $label = 'default-val')
    {
        $this->label = $label;
    }
}
