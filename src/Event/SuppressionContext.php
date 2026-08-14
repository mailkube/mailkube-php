<?php

declare(strict_types=1);

namespace Mailkube\Event;

use Mailkube\Internal\Payload;

/**
 * The recipients suppressed for a send, carried by `email.suppressed`.
 */
final class SuppressionContext
{
    /**
     * Describe the suppression.
     *
     * @phpstan-param list<string> $recipients
     */
    public function __construct(
        public readonly array $recipients,
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
            recipients: Payload::strings($data, 'recipients'),
            timestamp: Payload::text($data, 'timestamp') ?? '',
        );
    }
}
