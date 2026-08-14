<?php

declare(strict_types=1);

namespace Mailkube\Exception;

/**
 * An error returned by the API as a ``{name, message, statusCode}`` envelope.
 *
 * The HTTP status chooses the concrete subclass; the envelope's ``name`` stays a plain string
 * on {@see self::$errorName} rather than becoming a class of its own, because that list grows
 * unboundedly and ports badly. Compare it against {@see \Mailkube\ErrorName} for the
 * documented values.
 */
class ApiException extends MailkubeException
{
    /**
     * Capture the server's error envelope alongside the transport metadata a caller needs.
     *
     * The array shape is declared with @phpstan-param rather than @param: phpcs's Squiz
     * sniff enforces the legacy "integer" type vocabulary on @param tags, which contradicts
     * both the native types and phpstan. Native hints stay the source of truth.
     *
     * @phpstan-param array<string, mixed>|null $body
     */
    public function __construct(
        public readonly string $errorName,
        string $message,
        public readonly int $statusCode,
        public readonly ?array $body = null,
        public readonly ?int $retryAfter = null,
        public readonly ?string $requestId = null,
    ) {
        parent::__construct($message !== '' ? $message : ($errorName !== '' ? $errorName : "HTTP {$statusCode}"));
    }
}
