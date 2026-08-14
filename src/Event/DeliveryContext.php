<?php

declare(strict_types=1);

namespace Mailkube\Event;

use Mailkube\Internal\Payload;

/**
 * A single-recipient delivery outcome, carried by `email.delivered` and `email.sent`.
 */
final class DeliveryContext
{
    /**
     * Describe one recipient's outcome.
     */
    public function __construct(
        public readonly string $recipient,
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
            recipient: Payload::text($data, 'recipient') ?? '',
            timestamp: Payload::text($data, 'timestamp') ?? '',
        );
    }
}
