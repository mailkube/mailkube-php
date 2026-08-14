<?php

declare(strict_types=1);

namespace Mailkube\Resource;

use DateTimeInterface;
use Generator;
use Mailkube\Contract\TypedTransport;
use Mailkube\Internal\RequestSpec;
use Mailkube\Internal\Serializer;
use Mailkube\Model\CanceledScheduledEmail;
use Mailkube\Model\ScheduledEmail;
use Mailkube\Model\ScheduledEmailPage;

/**
 * The `scheduled-emails` resource, reached as `$client->scheduledEmails`.
 *
 * A send carrying `scheduledAt` is accepted but not delivered yet; until it is due it lives in this
 * collection, where it can be listed, inspected, rescheduled or canceled, one at a time or a whole
 * batch at once through {@see self::$batches}.
 *
 * Nothing here validates. The server owns the rules (the due time must be in the future and inside
 * the plan's horizon, the id must be a UUID, a batch label is at most 64 characters) and its error
 * names are richer than anything this SDK would reproduce.
 */
final class ScheduledEmailsResource
{
    /** The `scheduled-emails/batches` sub-namespace. */
    public readonly ScheduledEmailBatchesResource $batches;

    /** Collection path, relative to the configured base URL. */
    private const PATH = 'scheduled-emails';

    /**
     * Bind the resource and its batch sub-namespace to the transport performing their requests.
     */
    public function __construct(private readonly TypedTransport $transport)
    {
        $this->batches = new ScheduledEmailBatchesResource($transport);
    }

    /**
     * List one page of scheduled emails.
     *
     * Every filter is optional and an omitted one is simply not applied. Only `scheduled`,
     * `canceled` and `failed` can be listed: a sent email has left the collection, so filtering on
     * `sent` is a validation error rather than an empty result.
     *
     * @phpstan-param string|list<string>|null $status
     */
    public function list(
        string|array|null $status = null,
        ?string $batchId = null,
        DateTimeInterface|string|null $scheduledAtGte = null,
        DateTimeInterface|string|null $scheduledAtLte = null,
        ?int $page = null,
    ): ScheduledEmailPage {
        return $this->transport->request(
            self::listSpec($status, $batchId, $scheduledAtGte, $scheduledAtLte, $page),
            ScheduledEmailPage::fromArray(...),
        );
    }

    /**
     * Iterate every scheduled email matching the filters, across all pages.
     *
     * Pages are fetched lazily by following the links the API returns, so abandoning the iterator
     * early costs nothing: no page is requested until the consumer has exhausted the previous one.
     *
     * @phpstan-param string|list<string>|null $status
     *
     * @phpstan-return Generator<int, ScheduledEmail, mixed, void>
     */
    public function iterAll(
        string|array|null $status = null,
        ?string $batchId = null,
        DateTimeInterface|string|null $scheduledAtGte = null,
        DateTimeInterface|string|null $scheduledAtLte = null,
        ?int $page = null,
    ): Generator {
        $current = $this->list($status, $batchId, $scheduledAtGte, $scheduledAtLte, $page);

        while (true) {
            // Deliberately not `yield from`: it preserves the source array's keys, so every page
            // would re-yield 0..n-1 and iterator_to_array() would keep only the last page.
            foreach ($current->data as $email) {
                yield $email;
            }

            $next = $current->pagination->steps->next;
            if ($next === null) {
                return;
            }

            // The absolute link travels whole; Config::buildUrl() refuses one off the API origin.
            $current = $this->transport->request(
                new RequestSpec(path: $next, method: 'GET'),
                ScheduledEmailPage::fromArray(...),
            );
        }
    }

    /**
     * Retrieve one scheduled email.
     */
    public function get(string $emailId): ScheduledEmail
    {
        return $this->transport->request(
            new RequestSpec(path: self::item($emailId), method: 'GET'),
            ScheduledEmail::fromArray(...),
        );
    }

    /**
     * Reschedule one scheduled email, optionally moving it into or out of a batch.
     */
    public function update(
        string $emailId,
        DateTimeInterface|string $scheduledAt,
        ?string $batchId = null,
    ): ScheduledEmail {
        $body = ['scheduled_at' => Serializer::toIso($scheduledAt)];
        if ($batchId !== null) {
            $body['batch_id'] = $batchId;
        }

        return $this->transport->request(
            new RequestSpec(path: self::item($emailId), method: 'PATCH', body: $body),
            ScheduledEmail::fromArray(...),
        );
    }

    /**
     * Cancel one scheduled email.
     */
    public function cancel(string $emailId): CanceledScheduledEmail
    {
        return $this->transport->request(
            new RequestSpec(path: self::item($emailId), method: 'DELETE'),
            CanceledScheduledEmail::fromArray(...),
        );
    }

    /**
     * Build the request listing scheduled emails, omitting every filter left null.
     *
     * @phpstan-param string|list<string>|null $status
     */
    private static function listSpec(
        string|array|null $status,
        ?string $batchId,
        DateTimeInterface|string|null $scheduledAtGte,
        DateTimeInterface|string|null $scheduledAtLte,
        ?int $page,
    ): RequestSpec {
        $filters = array_filter([
            'status' => $status,
            'batch_id' => $batchId,
            'scheduled_at_gte' => $scheduledAtGte,
            'scheduled_at_lte' => $scheduledAtLte,
            'page' => $page,
        ], static fn (mixed $value): bool => $value !== null);

        return new RequestSpec(
            path: self::PATH,
            method: 'GET',
            query: array_map(
                static fn (DateTimeInterface|string|int|array $value): string => Serializer::queryValue($value),
                $filters,
            ),
        );
    }

    /**
     * Return the path of one scheduled email, with the identifier escaped.
     *
     * Escaping is not cosmetic: an identifier carrying an encoded `?` or `/` would otherwise
     * re-target the request at a different route.
     */
    private static function item(string $emailId): string
    {
        return self::PATH . '/' . rawurlencode($emailId);
    }
}
