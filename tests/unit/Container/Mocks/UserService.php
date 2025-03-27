<?php

declare(strict_types=1);

namespace Tests\Unit\Container\Mocks;

class UserService
{
    public function __construct(Mailer $mailer)
    {
    }
}
