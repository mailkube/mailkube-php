<?php

declare(strict_types=1);

namespace Mailkube;

use DateTimeImmutable;
use Exception;
use JsonException;
use Mailkube\Event\EventCatalogue;
use Mailkube\Event\WebhookEvent;
use Mailkube\Exception\SignatureVerificationException;

/**
 * Verify inbound webhooks.
 *
 * Verification is a pure, dependency-free HMAC check over the raw request bytes: no HTTP, no
 * client instance, so you call it directly inside your webhook handler.
 *
 * Signature scheme: the signed input is ``"{id}.{timestamp}."`` followed by the **raw body**,
 * HMAC-SHA256 keyed by the endpoint's signing secret, hex-encoded, and sent as
 * ``X-Webhook-Sig: sha256=<hex>``. ``X-Webhook-Ts`` is an ISO-8601 timestamp checked for
 * freshness; ``X-Webhook-Id`` is stable across retries, so use it to deduplicate.
 *
 * Most receivers want {@see self::verify()}, which checks the signature and returns a typed event:
 *
 * ```php
 * $event = Webhooks::verify($request->getBody()->getContents(), $headers, $secret);
 *
 * if ($event instanceof EmailBouncedEvent) {
 *     suppress($event->data->bounce->recipient, $event->data->bounce->reason);
 * }
 * ```
 *
 * {@see self::sign()} is the mirror, for anything that has to produce a delivery rather than
 * consume one.
 */
final class Webhooks
{
    private const SIGNATURE_PREFIX = 'sha256=';

    private const DEFAULT_TOLERANCE_SECONDS = 300;

    /**
     * Verify a webhook's signature and timestamp freshness over the raw body.
     *
     * Verify against the **raw received bytes**. Never decode then re-encode, or the signature
     * will not match.
     *
     * @phpstan-param array<string, string> $headers
     *
     * @throws SignatureVerificationException If a header is missing, the timestamp is malformed
     *                                        or stale, or the signature does not match.
     */
    public static function verifySignature(
        string $payload,
        array $headers,
        string $secret,
        int $toleranceSeconds = self::DEFAULT_TOLERANCE_SECONDS,
    ): string {
        $id = self::header($headers, 'x-webhook-id');
        $timestamp = self::header($headers, 'x-webhook-ts');
        $signature = self::header($headers, 'x-webhook-sig');
        if ($id === null || $timestamp === null || $signature === null) {
            throw new SignatureVerificationException('Missing required webhook signature headers.');
        }

        self::checkFreshness($timestamp, $toleranceSeconds);

        $expected = self::signatureHex($id, $timestamp, $payload, $secret);
        $provided = str_starts_with($signature, self::SIGNATURE_PREFIX)
            ? substr($signature, strlen(self::SIGNATURE_PREFIX))
            : $signature;

        if (!hash_equals($expected, $provided)) {
            throw new SignatureVerificationException('Webhook signature mismatch.');
        }

        return $payload;
    }

    /**
     * Produce the ``X-Webhook-Sig`` value for a payload, the mirror of {@see self::verifySignature()}.
     *
     * This exists so anything that produces a delivery — a test fixture, a local replay tool, a
     * fake endpoint in your own suite — computes the signature the way this class verifies it.
     * A reimplementation from the docblock above agrees with its author's reading of the prose
     * rather than with this SDK, and the two drift silently. Production code verifies; it does
     * not sign.
     *
     * Freshness is not this method's concern: it signs the timestamp it is given, so replaying
     * an old capture reproduces the original signature exactly.
     *
     * @param string $id        The ``X-Webhook-Id`` value.
     * @param string $timestamp The ``X-Webhook-Ts`` value, ISO-8601.
     * @param string $payload   The raw body that will be sent.
     * @param string $secret    The endpoint's signing secret.
     *
     * @return string The header value, including the ``sha256=`` prefix.
     */
    public static function sign(string $id, string $timestamp, string $payload, string $secret): string
    {
        return self::SIGNATURE_PREFIX . self::signatureHex($id, $timestamp, $payload, $secret);
    }

    /**
     * Parse a raw webhook body into a typed event.
     *
     * An unrecognized ``type`` yields an {@see \Mailkube\Event\UnknownEvent} rather than raising,
     * so a new server-side event never breaks a receiver that has not upgraded. A body that is not
     * a JSON object yields one too, carrying an empty payload.
     */
    public static function parseEvent(string $payload): WebhookEvent
    {
        return EventCatalogue::build(self::decode($payload));
    }

    /**
     * Verify a webhook and return the typed event it carries.
     *
     * The combination most receivers want: {@see self::verifySignature()} followed by
     * {@see self::parseEvent()}, over the same raw bytes.
     *
     * @phpstan-param array<string, string> $headers
     *
     * @throws SignatureVerificationException If verification fails.
     */
    public static function verify(
        string $payload,
        array $headers,
        string $secret,
        int $toleranceSeconds = self::DEFAULT_TOLERANCE_SECONDS,
    ): WebhookEvent {
        return self::parseEvent(self::verifySignature($payload, $headers, $secret, $toleranceSeconds));
    }

    /**
     * Return the hex HMAC-SHA256 over the contract's signed input.
     *
     * One implementation, so signing and verifying cannot disagree.
     */
    private static function signatureHex(string $id, string $timestamp, string $payload, string $secret): string
    {
        return hash_hmac('sha256', "{$id}.{$timestamp}." . $payload, $secret);
    }

    /**
     * Decode a webhook body, treating anything that is not a JSON object as an empty payload.
     *
     * @phpstan-return array<string, mixed>
     */
    private static function decode(string $payload): array
    {
        try {
            /** @var mixed $decoded */
            $decoded = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return [];
        }

        /** @var array<string, mixed> */
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Raise if the ISO-8601 timestamp is outside the tolerance window.
     *
     * @throws SignatureVerificationException If the timestamp is malformed or stale.
     */
    private static function checkFreshness(string $timestamp, int $toleranceSeconds): void
    {
        try {
            $parsed = new DateTimeImmutable($timestamp);
        } catch (Exception $exception) {
            throw new SignatureVerificationException('Malformed X-Webhook-Ts timestamp.', 0, $exception);
        }

        if (abs(time() - $parsed->getTimestamp()) > $toleranceSeconds) {
            throw new SignatureVerificationException('Webhook timestamp is outside the freshness window.');
        }
    }

    /**
     * Case-insensitive header lookup.
     *
     * @phpstan-param array<string, string> $headers
     */
    private static function header(array $headers, string $name): ?string
    {
        foreach ($headers as $key => $value) {
            if (strtolower($key) === $name) {
                return $value;
            }
        }

        return null;
    }
}
