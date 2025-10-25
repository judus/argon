<?php

declare(strict_types=1);

namespace Maduser\Argon\Container\Support {
    if (!function_exists(__NAMESPACE__ . '\\json_encode')) {
        function json_encode(mixed $value, int $flags = 0, int $depth = 512): string|false
        {
            if (!empty($GLOBALS['__debug_trace_force_json_failure'])) {
                return false;
            }

            if ($depth < 1) {
                $depth = 1;
            }

            /** @psalm-suppress InvalidArgument */
            return \json_encode($value, $flags, $depth);
        }
    }
}

namespace Tests\Unit\Container\Support {

    use Maduser\Argon\Container\Exceptions\ContainerException;
    use Maduser\Argon\Container\Support\DebugTrace;
    use Override;
    use PHPUnit\Framework\TestCase;
    use ReflectionException;
    use ReflectionMethod;
    use stdClass;

    final class DebugTraceTest extends TestCase
    {
        #[Override]
        protected function setUp(): void
        {
            parent::setUp();
            DebugTrace::reset();
        }

        public function testAddWithObject(): void
        {
            $value = new stdClass();
            DebugTrace::add('TestClass', 'param', 'stdClass', $value);

            $trace = DebugTrace::get();

            $this->assertSame('stdClass', $trace['TestClass']['param']['received']);
            $this->assertSame('stdClass', $trace['TestClass']['param']['expected']);
        }

        public function testAddWithClassString(): void
        {
            $value = stdClass::class;
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

        public function testNestStoresRawDataWhenNotStructured(): void
        {
            DebugTrace::add('RawClass', 'param', 'mixed', 'value');
            DebugTrace::nest('RawClass', 'param', ['custom' => 'payload']);

            $trace = DebugTrace::get();

            $this->assertSame(
                ['custom' => 'payload'],
                $trace['RawClass']['param']['resolved']
            );
        }

        public function testToJsonThrowsWhenEncodingFails(): void
        {
            DebugTrace::reset();
            DebugTrace::add('Failure', 'param', 'string', 'value');

            $GLOBALS['__debug_trace_force_json_failure'] = true;

            $this->expectException(ContainerException::class);
            $this->expectExceptionMessage('Failed to encode debug trace to JSON.');

            try {
                DebugTrace::toJson();
            } finally {
                unset($GLOBALS['__debug_trace_force_json_failure']);
            }
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

        /**
         * @throws ContainerException
         */
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

        public function testDiffReturnsOnlyNewEntries(): void
        {
            DebugTrace::add('InitialClass', 'param', 'string', 'value');
            $snapshot = DebugTrace::snapshot();

            DebugTrace::add('NewClass', 'param', 'int', 42);

            $diff = DebugTrace::diff($snapshot);

            $this->assertArrayNotHasKey('InitialClass', $diff);
            $this->assertArrayHasKey('NewClass', $diff);
        }

        public function testDiffDetectsChangedValues(): void
        {
            DebugTrace::add('ChangeClass', 'param', 'int', 42);
            $snapshot = DebugTrace::snapshot();

            DebugTrace::add('ChangeClass', 'param', 'int', 'foo');

            $diff = DebugTrace::diff($snapshot);

            $this->assertSame('string', $diff['ChangeClass']['param']['received']);
        }

        /**
         * @throws ReflectionException
         */
        public function testImportTraceMapSkipsNonArrayValues(): void
        {
            $method = new ReflectionMethod(DebugTrace::class, 'importTraceMap');
            /** @psalm-suppress UnusedMethodCall */
            $method->setAccessible(true);

            $result = $method->invoke(null, ['Class' => 'not-array']);

            $this->assertSame([], $result);
        }

        /**
         * @throws ReflectionException
         */
        public function testLooksLikeTraceMapReturnsFalseForEmptyArray(): void
        {
            $method = new ReflectionMethod(DebugTrace::class, 'looksLikeTraceMap');
            /** @psalm-suppress UnusedMethodCall */
            $method->setAccessible(true);

            $this->assertFalse($method->invoke(null, []));
        }
    }

}
