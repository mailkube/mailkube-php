<?php

declare(strict_types=1);

namespace Mailkube\Tests;

use Mailkube\Exception\SignatureVerificationException;
use Mailkube\Webhooks;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase as BaseTestCase;

/**
 * Webhook signature verification: HMAC over the raw bytes, plus freshness.
 */
final class WebhooksTest extends BaseTestCase
{
    private const SECRET = 'whsec_test';

    private const BODY = '{"type":"email.sent","data":{"id":"abc123"}}';

    private const FIXED_TIME = 1767225600;

    public function testAValidSignatureReturnsTheRawBody(): void
    {
        self::assertSame(self::BODY, Webhooks::verifySignature(self::BODY, self::signedHeaders(), self::SECRET));
    }

    public function testTheSha256PrefixIsOptional(): void
    {
        $headers = self::signedHeaders();
        $headers['X-Webhook-Sig'] = substr($headers['X-Webhook-Sig'], strlen('sha256='));

        self::assertSame(self::BODY, Webhooks::verifySignature(self::BODY, $headers, self::SECRET));
    }

    public function testHeadersAreMatchedCaseInsensitively(): void
    {
        $headers = array_change_key_case(self::signedHeaders());

        self::assertSame(self::BODY, Webhooks::verifySignature(self::BODY, $headers, self::SECRET));
    }

    public function testATamperedBodyFails(): void
    {
        $this->expectException(SignatureVerificationException::class);
        $this->expectExceptionMessage('signature mismatch');

        Webhooks::verifySignature('{"type":"email.bounced"}', self::signedHeaders(), self::SECRET);
    }

    public function testAWrongSecretFails(): void
    {
        $this->expectException(SignatureVerificationException::class);

        Webhooks::verifySignature(self::BODY, self::signedHeaders(), 'whsec_other');
    }

    #[DataProvider('requiredHeaders')]
    public function testAMissingHeaderFails(string $missing): void
    {
        $headers = self::signedHeaders();
        unset($headers[$missing]);

        $this->expectException(SignatureVerificationException::class);
        $this->expectExceptionMessage('Missing required');

        Webhooks::verifySignature(self::BODY, $headers, self::SECRET);
    }

    /**
     * @phpstan-return list<array{string}>
     */
    public static function requiredHeaders(): array
    {
        return [['X-Webhook-Id'], ['X-Webhook-Ts'], ['X-Webhook-Sig']];
    }

    public function testAStaleTimestampFails(): void
    {
        $this->expectException(SignatureVerificationException::class);
        $this->expectExceptionMessage('freshness window');

        Webhooks::verifySignature(self::BODY, self::signedHeaders(time() - 3600), self::SECRET);
    }

    public function testAMalformedTimestampFails(): void
    {
        $headers = self::signedHeaders();
        $headers['X-Webhook-Ts'] = 'not-a-date';
        $headers['X-Webhook-Sig'] = 'sha256=' . hash_hmac(
            'sha256',
            'wh_1.not-a-date.' . self::BODY,
            self::SECRET,
        );

        $this->expectException(SignatureVerificationException::class);
        $this->expectExceptionMessage('Malformed');

        Webhooks::verifySignature(self::BODY, $headers, self::SECRET);
    }

    /**
     * ``sign`` is tied to that oracle from both directions: the value it produces, and the
     * verifier's acceptance of it.
     */
    public function testSignMatchesAnIndependentlyComputedSignature(): void
    {
        $expected = self::signedHeaders(self::FIXED_TIME)['X-Webhook-Sig'];

        self::assertSame(
            $expected,
            Webhooks::sign('wh_1', gmdate('c', self::FIXED_TIME), self::BODY, self::SECRET),
        );
    }

    public function testTheVerifierAcceptsWhatSignProduces(): void
    {
        $timestamp = gmdate('c');
        $headers = [
            'X-Webhook-Id' => 'wh_1',
            'X-Webhook-Ts' => $timestamp,
            'X-Webhook-Sig' => Webhooks::sign('wh_1', $timestamp, self::BODY, self::SECRET),
        ];

        self::assertSame(self::BODY, Webhooks::verifySignature(self::BODY, $headers, self::SECRET));
    }

    /**
     * Build correctly-signed headers for the canonical body.
     *
     * Computes the HMAC directly rather than calling {@see Webhooks::sign()}: this is the oracle
     * every test above verifies against, so it must not share an implementation with the code
     * under test.
     *
     * @phpstan-return array<string, string>
     */
    private static function signedHeaders(?int $at = null): array
    {
        $timestamp = gmdate('c', $at ?? time());
        $signature = hash_hmac('sha256', "wh_1.{$timestamp}." . self::BODY, self::SECRET);

        return [
            'X-Webhook-Id' => 'wh_1',
            'X-Webhook-Ts' => $timestamp,
            'X-Webhook-Sig' => 'sha256=' . $signature,
        ];
    }
}
