<?php

declare(strict_types=1);

namespace Mailkube\Event;

use Mailkube\Internal\Payload;

/**
 * A per-recipient delivery failure, carried by `email.bounced` and `email.delivery_delayed`.
 *
 * `code` is the SMTP status the receiving server returned and `reason` its text. Both are
 * server-controlled: `reason` stays a plain string so a wording change cannot break parsing.
 */
final class FailureContext
{
    /**
     * Describe one recipient's failure.
     */
    public function __construct(
        public readonly string $recipient,
        public readonly string $timestamp,
        public readonly int $code,
        public readonly string $reason,
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
            recipient: Payload::text($data, 'recipient') ?? '',
            timestamp: Payload::text($data, 'timestamp') ?? '',
            code: Payload::integer($data, 'code'),
            reason: Payload::text($data, 'reason') ?? '',
        );
    }
}
