<?php

declare(strict_types=1);

namespace Mailkube\Model;

use Mailkube\Internal\Payload;

/**
 * The acknowledgement of a canceled scheduled email.
 *
 * The cancel verb answers `200` with this body rather than an empty `204`, so the caller can
 * confirm which email was canceled without a second request.
 */
final class CanceledScheduledEmail
{
    /**
     * Describe the canceled email.
     */
    public function __construct(
        public readonly string $id,
        public readonly string $object = 'scheduled_email',
        public readonly string $status = 'canceled',
    ) {
    }

    /**
     * Create the acknowledgement from a decoded response body.
     *
     * @phpstan-param array<string, mixed> $body
     */
    public static function fromArray(array $body): self
    {
        return new self(
            id: Payload::text($body, 'id') ?? '',
            object: Payload::text($body, 'object') ?? 'scheduled_email',
            status: Payload::text($body, 'status') ?? 'canceled',
        );
    }
}
