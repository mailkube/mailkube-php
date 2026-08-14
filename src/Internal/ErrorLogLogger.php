<?php

declare(strict_types=1);

namespace Mailkube\Internal;

use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;
use Stringable;

/**
 * A minimal PSR-3 logger writing through PHP's `error_log()`.
 *
 * This exists so the SDK has something to hand a caller who wants output without installing a
 * logging framework: on CLI `error_log()` goes to stderr, elsewhere to whatever the SAPI is
 * configured with. It is never installed automatically except through `MAILKUBE_LOG`.
 *
 * Records below the configured minimum are dropped here rather than at the call site, so
 * `MAILKUBE_LOG=warning` genuinely suppresses the debug request/response records instead of
 * printing them.
 */
final class ErrorLogLogger extends AbstractLogger
{
    /**
     * PSR-3 levels in descending severity. The index is the severity rank: lower is more severe.
     */
    private const SEVERITY = [
        LogLevel::EMERGENCY => 0,
        LogLevel::ALERT => 1,
        LogLevel::CRITICAL => 2,
        LogLevel::ERROR => 3,
        LogLevel::WARNING => 4,
        LogLevel::NOTICE => 5,
        LogLevel::INFO => 6,
        LogLevel::DEBUG => 7,
    ];

    private readonly int $threshold;

    /**
     * Bind the logger to the least severe level it will emit.
     *
     * An unrecognised level degrades to `debug` (emit everything) rather than throwing: this value
     * usually arrives from the `MAILKUBE_LOG` environment variable, and a typo there must not take
     * the calling application down.
     */
    public function __construct(string $minimumLevel = LogLevel::DEBUG)
    {
        $this->threshold = self::SEVERITY[strtolower($minimumLevel)] ?? self::SEVERITY[LogLevel::DEBUG];
    }

    /**
     * Write one record, unless it is less severe than the configured minimum.
     *
     * @phpstan-param array<mixed> $context
     */
    public function log(mixed $level, string|Stringable $message, array $context = []): void
    {
        $name = strtolower(is_string($level) ? $level : LogLevel::DEBUG);
        $severity = self::SEVERITY[$name] ?? null;
        if ($severity === null || $severity > $this->threshold) {
            return;
        }

        $suffix = $context === [] ? '' : ' ' . self::encode($context);
        error_log(sprintf('mailkube.%s: %s%s', $name, $message, $suffix));
    }

    /**
     * Render a context array for the log line, degrading to a placeholder if it cannot be encoded.
     *
     * @phpstan-param array<mixed> $context
     */
    private static function encode(array $context): string
    {
        $encoded = json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return $encoded === false ? '{"context":"unencodable"}' : $encoded;
    }
}
