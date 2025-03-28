<?php

namespace Tests\Unit\Container\Mocks;

class UnionExample {
    public function __construct(public Logger|Mailer|SomethingElse $dependency) {}
}