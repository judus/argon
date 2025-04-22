<?php

declare(strict_types=1);

namespace Tests\Integration\Compiler\Mocks;

class MailerFactory
{
    public function create(): Mailer
    {
        return new Mailer(new Logger());
    }

    public function createWithDefault(string $label = 'default-label'): DefaultValueService
    {
        return new DefaultValueService($label);
    }

    public function createWithRequired(string $label): DefaultValueService
    {
        return new DefaultValueService($label);
    }
}
