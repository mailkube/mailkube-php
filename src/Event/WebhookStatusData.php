<?php

declare(strict_types=1);

namespace Mailkube\Event;

use Mailkube\Internal\Payload;

/**
 * The data of a `webhook.status` event: an endpoint was enabled, disabled or deleted.
 *
 * Carries no message context. `disabledReason` is a server-controlled string, never an enum.
 * Receivers use this to notice their own endpoint being auto-disabled after repeated failures.
 */
final class WebhookStatusData
{
    /**
     * Describe the endpoint's new state and the state it left.
     */
    public function __construct(
        public readonly string $endpointUrl,
        public readonly bool $isActive,
        public readonly bool $isDeleted,
        public readonly string $disabledReason,
        public readonly WebhookStatusPrevious $previous,
    ) {
    }

    /**
     * Build the data block from a decoded event payload.
     *
     * @phpstan-param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            endpointUrl: Payload::text($data, 'endpoint_url') ?? '',
            isActive: Payload::boolean($data, 'is_active'),
            isDeleted: Payload::boolean($data, 'is_deleted'),
            disabledReason: Payload::text($data, 'disabled_reason') ?? '',
            previous: WebhookStatusPrevious::fromArray(Payload::nested($data, 'previous')),
        );
    }
}
