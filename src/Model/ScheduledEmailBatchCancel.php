<?php

declare(strict_types=1);

namespace Mailkube\Model;

use Mailkube\Internal\Payload;

/**
 * The result of canceling every pending email in a batch.
 *
 * An unknown batch is a no-op reporting `canceledCount: 0`, not an error, so a caller can cancel
 * defensively without first checking whether the batch exists.
 *
 * Deliberately a separate class from {@see ScheduledEmailBatchUpdate}: the two wire shapes are
 * disjoint, and one class carrying both counts would leave every caller inspecting which of them
 * is meaningful.
 */
final class ScheduledEmailBatchCancel
{
    /**
     * Describe the batch cancel.
     */
    public function __construct(
        public readonly string $batchId = '',
        public readonly int $canceledCount = 0,
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
            canceledCount: Payload::integer($body, 'canceled_count'),
            object: Payload::text($body, 'object') ?? 'scheduled_email.batch',
        );
    }
}
