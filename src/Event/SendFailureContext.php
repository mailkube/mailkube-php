<?php

declare(strict_types=1);

namespace Mailkube\Event;

use Mailkube\Internal\Payload;

/**
 * A message-level dispatch failure, carried by `email.failed`.
 *
 * Deliberately not {@see FailureContext}: `email.failed` means the message was dropped before it
 * was ever transmitted, so there is no recipient and no SMTP code to report. `reason` is a stable
 * server-side code such as `suppressed_at_dispatch` or `mta_unreachable`, kept as a plain string
 * so a new value never breaks an already-released client.
 */
final class SendFailureContext
{
    /**
     * Describe the dispatch failure.
     */
    public function __construct(
        public readonly string $reason,
        public readonly string $timestamp,
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
            reason: Payload::text($data, 'reason') ?? '',
            timestamp: Payload::text($data, 'timestamp') ?? '',
        );
    }
}
