<?php 
namespace Tests\Mocks;

class TestServiceWithNonExistentDependency
{
    public function __construct(NonExistentDependency $dependency)
    {
    }
}