<?php

declare(strict_types=1);

namespace Mailkube\Model;

use Mailkube\Internal\Payload;

/**
 * The result of rescheduling every pending email in a batch.
 *
 * `scheduledAt` is the due time the server applied, echoed back so a caller can confirm what the
 * batch actually moved to rather than assuming its own value was accepted verbatim.
 */
final class ScheduledEmailBatchUpdate
{
    /**
     * Describe the batch reschedule.
     */
    public function __construct(
        public readonly string $batchId = '',
        public readonly int $rescheduledCount = 0,
        public readonly ?string $scheduledAt = null,
        public readonly string $object = 'scheduled_email.batch',
    ) {
    }

    /**
     * Create the result from a decoded response body.
     *
     * @phpstan-param array<string, mixed> $body
     */
    public static function fromArray(array $body): self
    {
        return new self(
            batchId: Payload::text($body, 'batch_id') ?? '',
            rescheduledCount: Payload::integer($body, 'rescheduled_count'),
            scheduledAt: Payload::text($body, 'scheduled_at'),
            object: Payload::text($body, 'object') ?? 'scheduled_email.batch',
        );
    }
}
