<?php

declare(strict_types=1);

namespace Mailkube;

use Mailkube\Internal\ErrorLogLogger;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

/**
 * Opt-in logging for callers who do not already have a PSR-3 logger to hand.
 *
 * The SDK is silent by default and never configures logging for you. There are two ways to turn it
 * on, and both end with a PSR-3 logger reaching {@see Client}:
 *
 * ```php
 * $client = new Client(logger: Logging::errorLog());          // explicit
 * $client = new Client();                                     // or set MAILKUBE_LOG=debug
 * ```
 *
 * `MAILKUBE_LOG` holds a **level**, not a flag: `MAILKUBE_LOG=warning` suppresses the SDK's
 * request/response records, which are emitted at debug. An explicitly injected logger always wins
 * over the environment.
 *
 * The SDK logs the request method, URL and **redacted** headers, plus the response status and
 * request id. It never logs a request or response body: those carry recipient addresses, subjects
 * and message content.
 */
final class Logging
{
    /**
     * Environment variable naming the minimum level for the built-in logger.
     */
    public const ENV_LEVEL = 'MAILKUBE_LOG';

    /**
     * Return a logger writing through PHP's `error_log()` (stderr on CLI).
     *
     * For applications with no logging framework. Pass it to `new Client(logger: ...)`.
     */
    public static function errorLog(string $minimumLevel = LogLevel::DEBUG): LoggerInterface
    {
        return new ErrorLogLogger($minimumLevel);
    }

    /**
     * Return the logger requested by `MAILKUBE_LOG`, or null when the variable is unset or empty.
     */
    public static function fromEnvironment(): ?LoggerInterface
    {
        $level = getenv(self::ENV_LEVEL);
        if ($level === false || $level === '') {
            return null;
        }

        return self::errorLog($level);
    }
}
