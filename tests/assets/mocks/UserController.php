<?php

namespace Tests\Mocks;

class UserController
{
    private SaveUserRequest $request;

    private string $someValue = 'Hello';

    public function __construct(SaveUserRequest $request)
    {
        $this->request = $request;
    }

    public function action()
    {
        dump('UserController::action()');

        return $this->request;
    }

    public function setSomeValue(string $value): void
    {
        $this->someValue = $value;
    }

    public function getSomeValue(): string
    {
        return $this->someValue;
    }
}
