<?php

declare(strict_types=1);

namespace Mailkube\Internal;

/**
 * Removes credentials from header maps before they reach a log record.
 *
 * A class of its own, and the only thing that knows which headers are sensitive, so a future log
 * site cannot accidentally ship a bearer token by forgetting to redact. The header names come from
 * the shared SDK contract and are matched case-insensitively, because a PSR-7 message preserves
 * whatever casing the caller used.
 */
final class Redactor
{
    /** Lowercased header names whose values must never be logged. */
    private const SENSITIVE = ['authorization', 'idempotency-key'];

    /** Replacement written in place of a sensitive value. */
    private const MASK = '***';

    /**
     * Return a copy of $headers with every sensitive value replaced by a mask.
     *
     * @phpstan-param array<string, string> $headers
     *
     * @phpstan-return array<string, string>
     */
    public static function headers(array $headers): array
    {
        $redacted = [];
        foreach ($headers as $name => $value) {
            $redacted[$name] = in_array(strtolower($name), self::SENSITIVE, true) ? self::MASK : $value;
        }

        return $redacted;
    }
}
