<?php

declare(strict_types=1);

namespace Mailkube\Tests;

use DateTimeImmutable;
use DateTimeZone;
use Http\Client\Exception\NetworkException;
use Mailkube\Client;
use Mailkube\ErrorName;
use Mailkube\Exception\ApiException;
use Mailkube\Exception\AuthenticationException;
use Mailkube\Exception\BadRequestException;
use Mailkube\Exception\ConflictException;
use Mailkube\Exception\ConnectionException;
use Mailkube\Exception\InvalidRequestException;
use Mailkube\Exception\MailkubeException;
use Mailkube\Exception\NotFoundException;
use Mailkube\Exception\RateLimitException;
use Mailkube\Exception\ServerException;
use Mailkube\Model\Attachment;
use Mailkube\Model\Tag;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Request;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The client: request shape, configuration, error mapping and the origin guard.
 */
final class ClientTest extends TestCase
{
    public function testSendPostsToTheEmailsEndpoint(): void
    {
        $this->client()->emails->send(from: 'a@x.com', to: 'b@y.com', subject: 'Hi', html: '<p>Hi</p>');

        $request = $this->sentRequest();
        self::assertSame('POST', $request->getMethod());
        self::assertSame(self::BASE_URL . 'emails', (string) $request->getUri());
        self::assertSame(
            ['from' => 'a@x.com', 'to' => 'b@y.com', 'subject' => 'Hi', 'html' => '<p>Hi</p>'],
            $this->sentBody(),
        );
    }

    public function testEveryRequestCarriesBearerAuthAndTheVersionedUserAgent(): void
    {
        $this->client()->emails->send(from: 'a@x.com', to: 'b@y.com', subject: 'Hi');

        $request = $this->sentRequest();
        self::assertSame('Bearer mk_test', $request->getHeaderLine('Authorization'));
        self::assertStringStartsWith('mailkube-php/', $request->getHeaderLine('User-Agent'));
    }

    public function testOptionalFieldsAreOmittedRatherThanSentAsNull(): void
    {
        $this->client()->emails->send(from: 'a@x.com', to: 'b@y.com', subject: 'Hi');

        self::assertSame(['from', 'to', 'subject'], array_keys($this->sentBody()));
    }

    public function testRecipientListsAndHeadersAreForwarded(): void
    {
        $this->client()->emails->send(
            from: 'a@x.com',
            to: ['b@y.com', 'c@y.com'],
            subject: 'Hi',
            cc: 'cc@y.com',
            bcc: ['bcc@y.com'],
            replyTo: 'reply@y.com',
            headers: ['In-Reply-To' => '<prev@x>'],
        );

        $body = $this->sentBody();
        self::assertSame(['b@y.com', 'c@y.com'], $body['to']);
        self::assertSame('cc@y.com', $body['cc']);
        self::assertSame(['bcc@y.com'], $body['bcc']);
        self::assertSame('reply@y.com', $body['reply_to']);
        self::assertSame(['In-Reply-To' => '<prev@x>'], $body['headers']);
    }

    public function testAttachmentsAreBase64EncodedAndTagsRendered(): void
    {
        $this->client()->emails->send(
            from: 'a@x.com',
            to: 'b@y.com',
            subject: 'Hi',
            attachments: [Attachment::fromBytes('a.txt', 'hello', 'text/plain')],
            tags: [new Tag('campaign', 'spring')],
        );

        $body = $this->sentBody();
        self::assertSame(
            [['filename' => 'a.txt', 'content' => 'aGVsbG8=', 'content_type' => 'text/plain']],
            $body['attachments'],
        );
        self::assertSame([['name' => 'campaign', 'value' => 'spring']], $body['tags']);
    }

    public function testAPreEncodedAttachmentIsNotEncodedTwice(): void
    {
        $this->client()->emails->send(
            from: 'a@x.com',
            to: 'b@y.com',
            subject: 'Hi',
            attachments: [Attachment::fromBase64('a.txt', 'aGVsbG8=')],
        );

        self::assertSame([['filename' => 'a.txt', 'content' => 'aGVsbG8=']], $this->sentBody()['attachments']);
    }

    public function testTheIdempotencyKeyTravelsAsAHeaderNotInTheBody(): void
    {
        $this->client()->emails->send(from: 'a@x.com', to: 'b@y.com', subject: 'Hi', idempotencyKey: 'key-1');

        self::assertSame('key-1', $this->sentRequest()->getHeaderLine('Idempotency-Key'));
        self::assertArrayNotHasKey('idempotency_key', $this->sentBody());
    }

    public function testScheduledAtIsRenderedAsIso8601(): void
    {
        $this->client()->emails->send(
            from: 'a@x.com',
            to: 'b@y.com',
            subject: 'Hi',
            scheduledAt: new DateTimeImmutable('2026-08-20 07:00:00', new DateTimeZone('UTC')),
        );

        self::assertSame('2026-08-20T07:00:00+00:00', $this->sentBody()['scheduled_at']);
    }

    public function testSendReturnsTheParsedEmail(): void
    {
        $client = $this->client(body: '{"id":"abc123","message_id":"<abc123@msg>"}');
        $email = $client->emails->send(from: 'a@x.com', to: 'b@y.com', subject: 'Hi');

        self::assertSame('abc123', $email->id);
        self::assertSame('<abc123@msg>', $email->messageId);
        self::assertFalse($email->idempotentReplayed);
        self::assertFalse($email->isScheduled());
    }

    public function testAReplayedResponseIsReportedFromTheHeader(): void
    {
        $client = $this->client(headers: ['Idempotent-Replayed' => 'true']);

        self::assertTrue($client->emails->send(from: 'a@x.com', to: 'b@y.com', subject: 'Hi')->idempotentReplayed);
    }

    public function testAScheduledAckWidensTheSameModelRatherThanReturningAUnion(): void
    {
        $client = $this->client(202, '{"id":"abc123","status":"scheduled","scheduled_at":"2026-08-20T07:00:00Z"}');
        $email = $client->emails->send(from: 'a@x.com', to: 'b@y.com', subject: 'Hi', scheduledAt: '2026-08-20T07:00:00Z');

        self::assertTrue($email->isScheduled());
        self::assertSame('scheduled', $email->status);
    }

    /**
     * @phpstan-param class-string<ApiException> $expected
     */
    #[DataProvider('statusCases')]
    public function testStatusChoosesTheExceptionClass(int $status, string $expected): void
    {
        $client = $this->client($status, '{"name":"validation_error","message":"nope"}');

        $this->expectException($expected);
        $client->emails->send(from: 'a@x.com', to: 'b@y.com', subject: 'Hi');
    }

    /**
     * @phpstan-return list<array{int, class-string<ApiException>}>
     */
    public static function statusCases(): array
    {
        return [
            [400, BadRequestException::class],
            [403, AuthenticationException::class],
            [404, NotFoundException::class],
            [409, ConflictException::class],
            [422, InvalidRequestException::class],
            [429, RateLimitException::class],
            [500, ServerException::class],
            [503, ServerException::class],
            [418, ApiException::class],
        ];
    }

    public function testRetryAfterAndRequestIdAreReadOffTheResponseHeaders(): void
    {
        $client = $this->client(
            429,
            '{"name":"rate_limit_exceeded","message":"slow down"}',
            ['Retry-After' => '30', 'X-Request-Id' => 'req_42'],
        );

        try {
            $client->emails->send(from: 'a@x.com', to: 'b@y.com', subject: 'Hi');
            self::fail('expected a RateLimitException');
        } catch (RateLimitException $exception) {
            self::assertSame(30, $exception->retryAfter);
            self::assertSame('req_42', $exception->requestId);
            self::assertSame(ErrorName::RateLimitExceeded->value, $exception->errorName);
        }
    }

    public function testTheRequestIdIsFoundWhateverCasingTheServerUsed(): void
    {
        // HTTP header names are case-insensitive and the gateway only began sending this header
        // recently, so a case-sensitive lookup would have gone unnoticed: requestId was null on
        // every real response until then. PSR-7 guarantees the match; this pins it.
        $client = $this->client(500, '{"name":"application_error","message":"boom"}', ['x-request-id' => 'req_7']);

        try {
            $client->emails->send(from: 'a@x.com', to: 'b@y.com', subject: 'Hi');
            self::fail('expected a ServerException');
        } catch (ServerException $exception) {
            self::assertSame('req_7', $exception->requestId);
        }
    }

    public function testAnUnparseableRetryAfterIsIgnoredRatherThanThrowing(): void
    {
        $client = $this->client(429, '{"name":"rate_limit_exceeded"}', ['Retry-After' => 'soon']);

        try {
            $client->emails->send(from: 'a@x.com', to: 'b@y.com', subject: 'Hi');
            self::fail('expected a RateLimitException');
        } catch (RateLimitException $exception) {
            self::assertNull($exception->retryAfter);
        }
    }

    public function testAnUnknownErrorNameIsReportedVerbatim(): void
    {
        $client = $this->client(400, '{"name":"invented_next_year","message":"hi"}');

        try {
            $client->emails->send(from: 'a@x.com', to: 'b@y.com', subject: 'Hi');
            self::fail('expected a BadRequestException');
        } catch (BadRequestException $exception) {
            self::assertSame('invented_next_year', $exception->errorName);
        }
    }

    public function testAnUndecodableErrorBodyStillMapsByStatus(): void
    {
        $client = $this->client(500, '<html>oops</html>');

        $this->expectException(ServerException::class);
        $client->emails->send(from: 'a@x.com', to: 'b@y.com', subject: 'Hi');
    }

    public function testATransportFailureBecomesAConnectionException(): void
    {
        $this->http->addException(new NetworkException('boom', new Request('POST', self::BASE_URL . 'emails')));
        $factory = new Psr17Factory();
        $client = new Client(
            apiKey: 'mk_test',
            httpClient: $this->http,
            requestFactory: $factory,
            streamFactory: $factory,
        );

        $this->expectException(ConnectionException::class);
        $client->emails->send(from: 'a@x.com', to: 'b@y.com', subject: 'Hi');
    }

    public function testASuccessBodyWithoutAnIdIsAnSdkErrorNotAnApiError(): void
    {
        $client = $this->client(body: '{"unexpected":true}');

        $this->expectException(MailkubeException::class);
        $client->emails->send(from: 'a@x.com', to: 'b@y.com', subject: 'Hi');
    }
}
