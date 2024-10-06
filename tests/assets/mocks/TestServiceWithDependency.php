<?php 
namespace Tests\Mocks;

class TestServiceWithDependency
{
    public function __construct(NonExistentDependency $dependency)
    {
    }
}