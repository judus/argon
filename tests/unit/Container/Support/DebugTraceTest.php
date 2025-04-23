<?php

declare(strict_types=1);

namespace Tests\Unit\Container\Support;

use PHPUnit\Framework\TestCase;
use Maduser\Argon\Container\Support\DebugTrace;

final class DebugTraceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DebugTrace::reset();
    }

    public function testAddWithObject(): void
    {
        $value = new \stdClass();
        DebugTrace::add('TestClass', 'param', 'stdClass', $value);

        $trace = DebugTrace::get();

        $this->assertSame('stdClass', $trace['TestClass']['param']['received']);
        $this->assertSame('stdClass', $trace['TestClass']['param']['expected']);
    }

    public function testAddWithClassString(): void
    {
        $value = \stdClass::class;
        DebugTrace::add('TestClass', 'param', 'class-string', $value);

        $trace = DebugTrace::get();

        $this->assertSame('stdClass (class-string)', $trace['TestClass']['param']['received']);
        $this->assertSame('class-string', $trace['TestClass']['param']['expected']);
    }

    public function testAddWithScalar(): void
    {
        DebugTrace::add('TestClass', 'param', 'int', 42);

        $trace = DebugTrace::get();

        $this->assertSame('integer', $trace['TestClass']['param']['received']);
        $this->assertSame('int', $trace['TestClass']['param']['expected']);
    }

    public function testFail(): void
    {
        DebugTrace::fail('TestClass', 'param', 'string');

        $trace = DebugTrace::get();

        $this->assertSame('string', $trace['TestClass']['param']['expected']);
        $this->assertSame('unresolved', $trace['TestClass']['param']['error']);
    }

    public function testNestCreatesResolved(): void
    {
        DebugTrace::add('TestClass', 'param', 'array', []);
        DebugTrace::nest('TestClass', 'param', ['nested' => ['expected' => 'int']]);

        $trace = DebugTrace::get();

        $this->assertArrayHasKey('resolved', $trace['TestClass']['param']);
        $this->assertSame(['nested' => ['expected' => 'int']], $trace['TestClass']['param']['resolved']);
    }

    public function testNestOnUninitialized(): void
    {
        DebugTrace::nest('AnotherClass', 'anotherParam', ['foo' => ['expected' => 'bar']]);

        $trace = DebugTrace::get();

        $this->assertArrayHasKey('resolved', $trace['AnotherClass']['anotherParam']);
        $this->assertSame(['foo' => ['expected' => 'bar']], $trace['AnotherClass']['anotherParam']['resolved']);
    }

    public function testHasErrorsReturnsTrueOnError(): void
    {
        DebugTrace::fail('TestClass', 'param', 'string');
        $this->assertTrue(DebugTrace::hasErrors());
    }

    public function testHasErrorsReturnsTrueOnNull(): void
    {
        DebugTrace::add('TestClass', 'param', 'string', null);
        $this->assertTrue(DebugTrace::hasErrors());
    }

    public function testHasErrorsReturnsFalse(): void
    {
        DebugTrace::add('TestClass', 'param', 'string', 'value');
        $this->assertFalse(DebugTrace::hasErrors());
    }

    public function testDumpReturnsTrace(): void
    {
        DebugTrace::add('TestClass', 'param', 'string', 'value');
        $this->assertSame(DebugTrace::get(), DebugTrace::dump());
    }

    public function testToJson(): void
    {
        DebugTrace::add('TestClass', 'param', 'string', 'value');
        $json = DebugTrace::toJson();

        $this->assertJson($json);
        $this->assertStringContainsString('"expected": "string"', $json);
    }

    public function testResetClearsTrace(): void
    {
        DebugTrace::add('TestClass', 'param', 'string', 'value');
        DebugTrace::reset();

        $this->assertSame([], DebugTrace::get());
    }
}
