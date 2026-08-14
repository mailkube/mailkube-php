<?php

declare(strict_types=1);

namespace Mailkube\Resource;

use DateTimeInterface;
use Mailkube\Contract\TypedTransport;
use Mailkube\Internal\RequestSpec;
use Mailkube\Internal\Serializer;
use Mailkube\Model\ScheduledEmailBatchCancel;
use Mailkube\Model\ScheduledEmailBatchUpdate;

/**
 * The `scheduled-emails/batches` sub-resource, reached as `$client->scheduledEmails->batches`.
 *
 * A `batchId` passed to `emails->send()` alongside `scheduledAt` groups sends together; these two
 * verbs then move or cancel the whole group in one request. The namespace mirrors the sub-path
 * rather than flattening to `updateBatch`.
 */
final class ScheduledEmailBatchesResource
{
    /** Collection path, relative to the configured base URL. */
    private const PATH = 'scheduled-emails/batches';

    /**
     * Bind the resource to the transport that performs its requests.
     */
    public function __construct(private readonly TypedTransport $transport)
    {
    }

    /**
     * Reschedule every pending email in a batch.
     *
     * The batch is identified by the path only. There is deliberately no `batchId` in the body: the
     * server ignores one, and sending it would suggest the caller can move a batch other than the
     * one they named.
     */
    public function update(string $batchId, DateTimeInterface|string $scheduledAt): ScheduledEmailBatchUpdate
    {
        $spec = new RequestSpec(
            path: self::item($batchId),
            method: 'PATCH',
            body: ['scheduled_at' => Serializer::toIso($scheduledAt)],
        );

        return $this->transport->request($spec, ScheduledEmailBatchUpdate::fromArray(...));
    }

    /**
     * Cancel every pending email in a batch.
     *
     * An unknown batch is a no-op reporting `canceledCount: 0`, not an error.
     */
    public function cancel(string $batchId): ScheduledEmailBatchCancel
    {
        $spec = new RequestSpec(path: self::item($batchId), method: 'DELETE');

        return $this->transport->request($spec, ScheduledEmailBatchCancel::fromArray(...));
    }

    /**
     * Return the path of one batch, with the label escaped.
     *
     * Escaping is not cosmetic: a label carrying an encoded `?` or `/` would otherwise re-target
     * the request at a different route.
     */
    private static function item(string $batchId): string
    {
        return self::PATH . '/' . rawurlencode($batchId);
    }
}
