<?php

declare(strict_types=1);

namespace Mailkube\Model;

use Mailkube\Internal\Payload;

/**
 * The result of a successful send.
 *
 * A **scheduled** send (one carrying ``scheduledAt``) is acknowledged with ``202`` and a richer
 * body; ``status``, ``scheduledAt`` and ``batchId`` are populated only then, and
 * {@see self::isScheduled()} is the discriminator. An immediate send leaves all three null.
 *
 * This is the worked example of the contract's **widen, never union** rule: one call can return
 * two shapes, and adding optional fields plus a boolean accessor keeps every existing caller's
 * type valid, where returning a union would not.
 *
 * Readonly, and unknown wire fields are ignored, so a server-side field addition can never break
 * an already-released client. Timestamps stay the verbatim ISO-8601 strings the server sent.
 */
final class Email
{
    /**
     * Build the accepted-send result.
     */
    public function __construct(
        public readonly string $id,
        public readonly ?string $messageId = null,
        public readonly bool $idempotentReplayed = false,
        public readonly ?string $status = null,
        public readonly ?string $scheduledAt = null,
        public readonly ?string $batchId = null,
    ) {
    }

    /**
     * Create an Email from a decoded response body, ignoring fields this release predates.
     *
     * @phpstan-param array<string, mixed> $body
     */
    public static function fromArray(array $body, bool $idempotentReplayed = false): self
    {
        return new self(
            id: Payload::text($body, 'id') ?? '',
            messageId: Payload::text($body, 'message_id'),
            idempotentReplayed: $idempotentReplayed,
            status: Payload::text($body, 'status'),
            scheduledAt: Payload::text($body, 'scheduled_at'),
            batchId: Payload::text($body, 'batch_id'),
        );
    }

    /**
     * Whether the send was accepted for later delivery rather than sent now.
     */
    public function isScheduled(): bool
    {
        return $this->scheduledAt !== null;
    }
}
