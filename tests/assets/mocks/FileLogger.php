<?php

namespace Tests\Mocks;

use Psr\Log\InvalidArgumentException;
use Stringable;

class FileLogger implements LoggerInterface
{
    /**
     * System is unusable.
     *
     * @param mixed[] $context
     */
    public function emergency(string|Stringable $message, array $context = []): void
    {
        echo "EMERGENCY: $message" .PHP_EOL;
    }

    /**
     * Action must be taken immediately.
     *
     * Example: Entire website down, database unavailable, etc. This should
     * trigger the SMS alerts and wake you up.
     *
     * @param mixed[] $context
     */
    public function alert(string|Stringable $message, array $context = []): void
    {
        echo "ALERT: $message" . PHP_EOL;
    }

    /**
     * Critical conditions.
     *
     * Example: Application component unavailable, unexpected exception.
     *
     * @param mixed[] $context
     */
    public function critical(string|Stringable $message, array $context = []): void
    {
        echo "CRITICAL: $message" . PHP_EOL;
    }

    /**
     * Runtime errors that do not require immediate action but should typically
     * be logged and monitored.
     *
     * @param mixed[] $context
     */
    public function error(string|Stringable $message, array $context = []): void
    {
        echo "ERROR: $message" . PHP_EOL;
    }

    /**
     * Exceptional occurrences that are not errors.
     *
     * Example: Use of deprecated APIs, poor use of an API, undesirable things
     * that are not necessarily wrong.
     *
     * @param mixed[] $context
     */
    public function warning(string|Stringable $message, array $context = []): void
    {
        echo "WARING: $message" . PHP_EOL;
    }

    /**
     * Normal but significant events.
     *
     * @param mixed[] $context
     */
    public function notice(string|Stringable $message, array $context = []): void
    {
        echo "NOTICE: $message" . PHP_EOL;
    }

    /**
     * Interesting events.
     *
     * Example: User logs in, SQL logs.
     *
     * @param mixed[] $context
     */
    public function info(string|Stringable $message, array $context = []): void
    {
        echo "INFO: $message" . PHP_EOL;
    }

    /**
     * Detailed debug information.
     *
     * @param mixed[] $context
     */
    public function debug(string|Stringable $message, array $context = []): void
    {
        echo "DEBUG: $message" . PHP_EOL;
    }

    /**
     * Logs with an arbitrary level.
     *
     * @param mixed   $level
     * @param mixed[] $context
     *
     * @throws InvalidArgumentException
     */
    public function log($level, string|Stringable $message, array $context = []): void
    {
        echo "LOG: $message" . PHP_EOL;
    }
}