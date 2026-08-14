<?php

declare(strict_types=1);

namespace Mailkube\Tests;

use DateTimeImmutable;
use DateTimeZone;
use Mailkube\ErrorName;
use Mailkube\Exception\ApiException;
use Mailkube\Exception\ConflictException;
use Mailkube\Exception\InvalidRequestException;
use Mailkube\Exception\MailkubeException;
use Mailkube\Exception\NotFoundException;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The scheduled-emails verbs: URLs, queries, bodies, parsed models and mapped errors.
 */
final class ScheduledEmailsTest extends TestCase
{
    /** One row as the server sends it, with every key populated. */
    private const ROW = <<<'JSON'
        {
          "id": "b3f1c2e4-0000-4000-8000-000000000001",
          "message_id": "<b3f1c2e4@msg.mailkube.com>",
          "object": "scheduled_email",
          "status": "scheduled",
          "scheduled_at": "2026-08-20T07:00:00Z",
          "created_at": "2026-08-13T09:00:00Z",
          "batch_id": "welcome-wave",
          "subject": "Hello world",
          "recipients": "customer@example.com +2",
          "topic": "product-updates",
          "tags": [{"name": "campaign", "value": "launch"}]
        }
        JSON;

    public function testGetRetrievesOneScheduledEmail(): void
    {
        $email = $this->client(200, self::ROW)->scheduledEmails->get('b3f1c2e4-0000-4000-8000-000000000001');

        $request = $this->sentRequest();
        self::assertSame('GET', $request->getMethod());
        self::assertSame(
            self::BASE_URL . 'scheduled-emails/b3f1c2e4-0000-4000-8000-000000000001',
            (string) $request->getUri(),
        );

        self::assertSame('b3f1c2e4-0000-4000-8000-000000000001', $email->id);
        self::assertSame('scheduled', $email->status);
        self::assertSame('welcome-wave', $email->batchId);
        self::assertSame('product-updates', $email->topic);
        // A summary string, not a list: the full recipient set stays server-side.
        self::assertSame('customer@example.com +2', $email->recipients);
        self::assertCount(1, $email->tags);
        self::assertSame('campaign', $email->tags[0]->name);
        self::assertSame('launch', $email->tags[0]->value);
    }

    public function testAnIdentifierIsEscapedIntoASinglePathSegment(): void
    {
        $this->client(200, self::ROW)->scheduledEmails->get('../batches/x?page=9');

        // Asserted against the RAW path: a decoded view would hide the traversal this prevents.
        self::assertSame(
            '/mta/v1/scheduled-emails/..%2Fbatches%2Fx%3Fpage%3D9',
            $this->sentRequest()->getUri()->getPath(),
        );
        self::assertSame('', $this->sentRequest()->getUri()->getQuery());
    }

    public function testUpdateSendsOnlyTheDueTimeWhenNoBatchIsGiven(): void
    {
        $this->client(200, self::ROW)->scheduledEmails->update('abc', '2026-08-21T07:00:00Z');

        self::assertSame('PATCH', $this->sentRequest()->getMethod());
        // Never `"batch_id": null` — an omitted field is absent from the wire.
        self::assertSame(['scheduled_at' => '2026-08-21T07:00:00Z'], $this->sentBody());
    }

    public function testUpdateAcceptsADateTimeAndMovesTheEmailIntoABatch(): void
    {
        $due = new DateTimeImmutable('2026-08-21T07:00:00', new DateTimeZone('UTC'));
        $this->client(200, self::ROW)->scheduledEmails->update('abc', $due, batchId: 'second-wave');

        self::assertSame(
            ['scheduled_at' => '2026-08-21T07:00:00+00:00', 'batch_id' => 'second-wave'],
            $this->sentBody(),
        );
    }

    public function testCancelAnswersTwoHundredWithABody(): void
    {
        $body = '{"id":"abc","object":"scheduled_email","status":"canceled"}';
        $canceled = $this->client(200, $body)->scheduledEmails->cancel('abc');

        self::assertSame('DELETE', $this->sentRequest()->getMethod());
        self::assertSame('abc', $canceled->id);
        self::assertSame('canceled', $canceled->status);
    }

    public function testBatchUpdateIdentifiesTheBatchByPathOnly(): void
    {
        $body = '{"object":"scheduled_email.batch","batch_id":"wave","rescheduled_count":4,'
            . '"scheduled_at":"2026-08-22T07:00:00Z"}';
        $result = $this->client(200, $body)->scheduledEmails->batches->update('wave', '2026-08-22T07:00:00Z');

        $request = $this->sentRequest();
        self::assertSame('PATCH', $request->getMethod());
        self::assertSame(self::BASE_URL . 'scheduled-emails/batches/wave', (string) $request->getUri());
        // No batch_id in the body: the path names the batch, and a second one could contradict it.
        self::assertSame(['scheduled_at' => '2026-08-22T07:00:00Z'], $this->sentBody());

        self::assertSame(4, $result->rescheduledCount);
        self::assertSame('2026-08-22T07:00:00Z', $result->scheduledAt);
    }

    public function testBatchCancelReportsACountAndTreatsAnUnknownBatchAsANoOp(): void
    {
        $body = '{"object":"scheduled_email.batch","batch_id":"ghost","canceled_count":0}';
        $result = $this->client(200, $body)->scheduledEmails->batches->cancel('ghost');

        self::assertSame('DELETE', $this->sentRequest()->getMethod());
        self::assertSame('ghost', $result->batchId);
        self::assertSame(0, $result->canceledCount);
    }

    public function testEveryFilterShapeIsSerializedIntoTheQuery(): void
    {
        $this->client(200, '{"pagination":{"steps":{}},"data":[]}')->scheduledEmails->list(
            status: ['scheduled', 'canceled'],
            batchId: 'wave',
            scheduledAtGte: new DateTimeImmutable('2026-08-01T00:00:00', new DateTimeZone('UTC')),
            scheduledAtLte: '2026-08-31T00:00:00Z',
            page: 2,
        );

        parse_str($this->sentRequest()->getUri()->getQuery(), $query);

        self::assertSame([
            'status' => 'scheduled,canceled',
            'batch_id' => 'wave',
            'scheduled_at_gte' => '2026-08-01T00:00:00+00:00',
            'scheduled_at_lte' => '2026-08-31T00:00:00Z',
            'page' => '2',
        ], $query);
    }

    public function testAnOmittedFilterIsNotSent(): void
    {
        $this->client(200, '{"pagination":{"steps":{}},"data":[]}')->scheduledEmails->list(status: 'scheduled');

        self::assertSame('status=scheduled', $this->sentRequest()->getUri()->getQuery());
    }

    /**
     * A verb that hydrates a model must refuse a 2xx body that is not a JSON object, rather than
     * fabricate a model with empty fields from it.
     */
    #[DataProvider('malformedSuccessBodies')]
    public function testAMalformedSuccessBodyIsAnSdkErrorNotAModel(string $body): void
    {
        $this->expectException(MailkubeException::class);
        $this->expectExceptionMessage('Expected a JSON object');

        $this->client(200, $body)->scheduledEmails->get('abc');
    }

    /**
     * @phpstan-return array<string, array{string}>
     */
    public static function malformedSuccessBodies(): array
    {
        return [
            'a bare string' => ['"nope"'],
            'a bare number' => ['3'],
            'an empty body' => [''],
            'undecodable' => ['{not json'],
        ];
    }

    /**
     * The status-to-class table applies to every verb, not just the send path.
     *
     * @phpstan-param class-string<ApiException> $expected
     */
    #[DataProvider('scheduledErrors')]
    public function testAnErrorStatusMapsToItsExceptionOnEveryVerb(int $status, string $name, string $expected): void
    {
        $client = $this->client($status, sprintf('{"name":"%s","message":"nope","statusCode":%d}', $name, $status));

        try {
            $client->scheduledEmails->get('abc');
            self::fail("expected a {$expected}");
        } catch (ApiException $exception) {
            self::assertInstanceOf($expected, $exception);
            self::assertSame($name, $exception->errorName);
            self::assertSame($status, $exception->statusCode);
        }
    }

    /**
     * @phpstan-return array<string, array{int, string, class-string<ApiException>}>
     */
    public static function scheduledErrors(): array
    {
        return [
            'unknown id' => [404, 'scheduled_email_not_found', NotFoundException::class],
            'already sent' => [422, 'scheduled_email_not_pending', InvalidRequestException::class],
            'idempotency conflict' => [409, 'invalid_idempotent_request', ConflictException::class],
        ];
    }

    public function testAnErrorNameConstantMatchesWhatTheServerSent(): void
    {
        // The point of the enum: a caller branches on it without retyping the wire string.
        $body = '{"name":"scheduled_email_not_pending","message":"nope","statusCode":422}';
        $client = $this->client(422, $body);

        try {
            $client->scheduledEmails->cancel('abc');
            self::fail('expected an InvalidRequestException');
        } catch (InvalidRequestException $exception) {
            self::assertSame(ErrorName::ScheduledEmailNotPending->value, $exception->errorName);
        }
    }
}
