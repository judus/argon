<?php

declare(strict_types=1);

namespace Tests\Integration\Compiler\Mocks;

class MailerFactory
{
    public function create(): Mailer
    {
        return new Mailer(new Logger());
    }
}
