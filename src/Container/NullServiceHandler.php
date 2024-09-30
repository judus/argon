<?php

namespace Maduser\Argon\Container;

class NullServiceHandler
{
    public function __call($name, $arguments)
    {
        return null;
    }
}