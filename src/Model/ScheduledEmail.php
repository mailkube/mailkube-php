<?php

declare(strict_types=1);

namespace Mailkube\Model;

use Mailkube\Internal\Payload;

/**
 * An email accepted for later delivery, as it appears in the scheduled collection.
 *
 * Returned by `list`, `get` and `update`. Readonly, and unknown wire fields are ignored, so a
 * server-side field addition can never break an already-released client. Timestamps stay the
 * verbatim ISO-8601 strings the server sent.
 *
 * Two fields deserve care:
 *
 * - **`recipients` is a summary string, not a list.** The server sends the first recipient plus an
 *   overflow count, e.g. `"customer@example.com +2"`. The full recipient list stays server-side
 *   with the frozen payload.
 * - **`status` is a plain string, never an enum.** `scheduled`, `canceled`, `sent` and `failed` are
 *   what the server sends today; a closed set here would turn a new server value into a parse error
 *   on a client that is already released.
 */
final class ScheduledEmail
{
    /**
     * Describe one scheduled email.
     *
     * @phpstan-param list<Tag> $tags
     */
    public function __construct(
        public readonly string $id,
        public readonly ?string $messageId = null,
        public readonly string $object = 'scheduled_email',
        public readonly string $status = '',
        public readonly ?string $scheduledAt = null,
        public readonly ?string $createdAt = null,
        public readonly ?string $batchId = null,
        public readonly ?string $subject = null,
        public readonly ?string $recipients = null,
        public readonly ?string $topic = null,
        public readonly array $tags = [],
    ) {
    }

    /**
     * Create a ScheduledEmail from a decoded response body, ignoring fields this release predates.
     *
     * @phpstan-param array<string, mixed> $body
     */
    public static function fromArray(array $body): self
    {
        return new self(
            id: Payload::text($body, 'id') ?? '',
            messageId: Payload::text($body, 'message_id'),
            object: Payload::text($body, 'object') ?? 'scheduled_email',
            status: Payload::text($body, 'status') ?? '',
            scheduledAt: Payload::text($body, 'scheduled_at'),
            createdAt: Payload::text($body, 'created_at'),
            batchId: Payload::text($body, 'batch_id'),
            subject: Payload::text($body, 'subject'),
            recipients: Payload::text($body, 'recipients'),
            topic: Payload::text($body, 'topic'),
            tags: Payload::listOf($body, 'tags', Tag::fromArray(...)),
        );
    }
}
