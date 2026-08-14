<?php

declare(strict_types=1);

namespace Mailkube\Event;

use Mailkube\Internal\Payload;

/**
 * An endpoint's state before the change that produced a `webhook.status` event.
 *
 * `disabledReason` is a server-controlled string, never an enum.
 */
final class WebhookStatusPrevious
{
    /**
     * Describe the previous state.
     */
    public function __construct(
        public readonly bool $isActive,
        public readonly bool $isDeleted,
        public readonly string $disabledReason,
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
            isActive: Payload::boolean($data, 'is_active'),
            isDeleted: Payload::boolean($data, 'is_deleted'),
            disabledReason: Payload::text($data, 'disabled_reason') ?? '',
        );
    }
}
