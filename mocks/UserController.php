<?php

namespace Maduser\Argon\Mocks;

class UserController
{
    private SaveUserRequest $request;
    private string $someValue = 'Hello';

    /**
     * @param SaveUserRequest $request
     */
    public function __construct(SaveUserRequest $request)
    {
        $this->request = $request;
    }

    public function action(): SaveUserRequest
    {
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