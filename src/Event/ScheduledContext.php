<?php

declare(strict_types=1);

namespace Mailkube\Event;

use Mailkube\Internal\Payload;

/**
 * When a scheduled send is due, carried by `email.scheduled`.
 *
 * Unlike the engagement blocks these keys are snake_case on the wire. `batchId` is null when the
 * send was not grouped, which is the only nullable field inside any nested block.
 */
final class ScheduledContext
{
    /**
     * Describe the schedule.
     */
    public function __construct(
        public readonly string $scheduledAt,
        public readonly ?string $batchId,
    ) {
    }

    /**
     * Build the block from a decoded event payload.
     *
     * @phpstan-param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            scheduledAt: Payload::text($data, 'scheduled_at') ?? '',
            batchId: Payload::text($data, 'batch_id'),
        );
    }
}
