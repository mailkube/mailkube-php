<?php

declare(strict_types=1);

namespace Mailkube\Tests\Support;

use Psr\Log\AbstractLogger;
use Stringable;

/**
 * A PSR-3 logger that keeps every record so a test can assert on it.
 *
 * Hand-written rather than pulled from a package: `Psr\Log\Test\TestLogger` was removed in
 * psr/log 2.x, and this is four lines.
 */
final class RecordingLogger extends AbstractLogger
{
    /** @phpstan-var list<array{level: string, message: string, context: array<mixed>}> */
    public array $records = [];

    /**
     * Record one log call verbatim.
     *
     * @phpstan-param array<mixed> $context
     */
    public function log(mixed $level, string|Stringable $message, array $context = []): void
    {
        $this->records[] = [
            'level' => is_string($level) ? $level : 'unknown',
            'message' => (string) $message,
            'context' => $context,
        ];
    }

    /**
     * Return every record rendered as one string, for "this value never appears" assertions.
     */
    public function dump(): string
    {
        return (string) json_encode($this->records);
    }
}
