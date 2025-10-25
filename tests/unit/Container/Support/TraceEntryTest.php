<?php

declare(strict_types=1);

namespace Tests\Unit\Container\Support;

use Maduser\Argon\Container\Support\TraceEntry;
use PHPUnit\Framework\TestCase;
use stdClass;

final class TraceEntryTest extends TestCase
{
    public function testForValueCreatesReceivedSignature(): void
    {
        $entry = TraceEntry::forValue('stdClass', new stdClass());

        $this->assertSame(
            [
                'expected' => 'stdClass',
                'received' => stdClass::class,
            ],
            $entry->toArray()
        );
        $this->assertFalse($entry->hasErrors());
        $this->assertSame('stdClass', $entry->getExpected());
        $this->assertSame(stdClass::class, $entry->getReceived());
        $this->assertNull($entry->getError());
    }

    public function testUnresolvedCreatesError(): void
    {
        $entry = TraceEntry::unresolved('Foo');

        $this->assertTrue($entry->hasErrors());
        $this->assertSame(
            [
                'expected' => 'Foo',
                'error' => 'unresolved',
            ],
            $entry->toArray()
        );
        $this->assertSame('unresolved', $entry->getError());
        $this->assertNull($entry->getReceived());
    }

    public function testResolvedDiffsStructuredEntries(): void
    {
        $base = TraceEntry::forValue('Foo', 'foo');
        $base->setResolved([
            'Child' => [
                'param' => TraceEntry::forValue('Bar', 'bar'),
            ],
        ]);

        $updated = TraceEntry::forValue('Foo', 'baz');
        $updated->setResolved([
            'Child' => [
                'param' => TraceEntry::unresolved('Bar'),
            ],
        ]);

        $diff = $updated->diff($base);

        $this->assertNotNull($diff);
        $this->assertSame(
            [
                'expected' => 'Foo',
                'received' => 'string',
                'resolved' => [
                    'Child' => [
                        'param' => [
                            'expected' => 'Bar',
                            'error' => 'unresolved',
                        ],
                    ],
                ],
            ],
            $diff->toArray()
        );
        $this->assertArrayHasKey('Child', $diff->getResolved());
    }

    public function testDiffTreatsNewResolvedEntryAsChange(): void
    {
        $baseline = TraceEntry::empty('foo');

        $current = TraceEntry::empty('foo');
        $current->setResolved([
            'NewClass' => ['param' => TraceEntry::forValue('int', 1)],
        ]);

        $diff = $current->diff($baseline);

        $this->assertNotNull($diff);
        $this->assertArrayHasKey('NewClass', $diff->getResolved());
    }

    public function testResolvedRawDataPreservedAndDiffed(): void
    {
        $raw = [
            'Nested' => [
                'param' => [
                    'expected' => 'int',
                    'received' => 'NULL',
                ],
            ],
        ];

        $entry = TraceEntry::empty();
        $entry->setResolvedRaw($raw);

        $this->assertTrue($entry->hasErrors(), 'raw NULL should be treated as error');
        $this->assertSame(
            [
                'expected' => 'unknown',
                'resolved' => $raw,
            ],
            $entry->toArray()
        );

        $diff = $entry->diff(TraceEntry::empty());
        $this->assertNotNull($diff);
        $this->assertSame(
            $raw,
            $diff->getResolvedRaw()
        );
    }

    public function testFromArrayHandlesRawAndStructuredData(): void
    {
        $payload = [
            'expected' => 'Foo',
            'received' => 'bar',
            'resolved' => [
                'Raw' => [
                    'param' => [
                        'expected' => 'int',
                        'error' => 'missing',
                    ],
                ],
            ],
        ];

        $entry = TraceEntry::fromArray($payload);

        $this->assertTrue($entry->hasErrors());
        $this->assertSame($payload, $entry->toArray());
    }

    public function testRawResolvedDetectsErrorFlag(): void
    {
        $entry = TraceEntry::empty('foo');
        $entry->setResolvedRaw(['payload' => ['error' => 'boom']]);

        $this->assertTrue($entry->hasErrors());
    }

    public function testFromArrayParsesStructuredResolvedEntries(): void
    {
        $payload = [
            'expected' => 'Foo',
            'resolved' => [
                'MyClass' => [
                    'param' => [
                        'expected' => 'int',
                        'received' => 'integer',
                    ],
                ],
            ],
        ];

        $entry = TraceEntry::fromArray($payload);
        $resolved = $entry->getResolved();

        $this->assertArrayHasKey('MyClass', $resolved);
        $this->assertArrayHasKey('param', $resolved['MyClass']);
        $this->assertSame('int', $resolved['MyClass']['param']->getExpected());
    }

    public function testFromArraySkipsInvalidResolvedChildren(): void
    {
        $payload = [
            'expected' => 'Foo',
            'resolved' => [
                'Broken' => 'string',
                'PartiallyInvalid' => [
                    'param' => 'not-array',
                    'valid' => ['expected' => 'int'],
                ],
            ],
        ];

        $entry = TraceEntry::fromArray($payload);

        $this->assertSame(
            [
                'expected' => 'Foo',
                'resolved' => [
                    'PartiallyInvalid' => [
                        'valid' => ['expected' => 'int'],
                    ],
                ],
            ],
            $entry->toArray()
        );
    }

    public function testFromArrayFallsBackToRawWhenNoStructuredEntries(): void
    {
        $payload = [
            'expected' => 'Foo',
            'resolved' => [
                'Broken' => 'string',
            ],
        ];

        $entry = TraceEntry::fromArray($payload);

        $this->assertSame($payload, $entry->toArray());
    }

    public function testSetResolvedRawExposesRawData(): void
    {
        $entry = TraceEntry::empty('foo');
        $entry->setResolvedRaw(['payload' => 'value']);

        $this->assertSame(['payload' => 'value'], $entry->getResolvedRaw());
        $this->assertSame([], $entry->getResolved());
    }

    public function testDiffReturnsNullWhenEntriesIdentical(): void
    {
        $base = TraceEntry::forValue('Foo', 'bar');
        $clone = TraceEntry::fromArray($base->toArray());

        $this->assertNull($clone->diff($base));
    }

    public function testRawDiffDetectsChanges(): void
    {
        $previous = TraceEntry::empty('foo');
        $previous->setResolvedRaw(['payload' => 'value']);

        $current = TraceEntry::empty('foo');
        $current->setResolvedRaw(['payload' => 'changed']);

        $diff = $current->diff($previous);

        $this->assertNotNull($diff);
        $this->assertSame(['payload' => 'changed'], $diff->getResolvedRaw());
    }

    public function testRawDiffHandlesNestedArrays(): void
    {
        $previous = TraceEntry::empty('foo');
        $previous->setResolvedRaw([
            'nested' => [
                'inner' => ['value' => 'old'],
            ],
        ]);

        $current = TraceEntry::empty('foo');
        $current->setResolvedRaw([
            'nested' => [
                'inner' => ['value' => 'new'],
            ],
        ]);

        $diff = $current->diff($previous);

        $this->assertNotNull($diff);
        $this->assertSame(
            ['nested' => ['inner' => ['value' => 'new']]],
            $diff->getResolvedRaw()
        );
    }

    public function testRawDiffReturnsNullWhenNestedStructuresMatch(): void
    {
        $previous = TraceEntry::empty('foo');
        $previous->setResolvedRaw([
            'nested' => [
                'inner' => ['value' => 'same'],
            ],
        ]);

        $current = TraceEntry::empty('foo');
        $current->setResolvedRaw([
            'nested' => [
                'inner' => ['value' => 'same'],
            ],
        ]);

        $diff = $current->diff($previous);

        $this->assertNull($diff);
    }


    public function testRawResolvedHasErrorsReturnsFalseWhenClean(): void
    {
        $entry = TraceEntry::empty('foo');
        $entry->setResolvedRaw(['scalar' => 'value', 'nested' => ['expected' => 'int', 'received' => 'int']]);

        $this->assertFalse($entry->hasErrors());
    }
}
