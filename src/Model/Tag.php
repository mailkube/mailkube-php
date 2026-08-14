<?php

declare(strict_types=1);

namespace Mailkube\Model;

use Mailkube\Internal\Payload;

/**
 * A free-form name/value tag attached to an outgoing email.
 *
 * Tags are forwarded to the server, which denormalizes them onto the sending log so you can
 * filter, export and dashboard sends by tag, and so they ride along on delivery webhooks.
 * Validation is server-side (the authoritative gate). Tag values are not encrypted, so do not
 * put personal data in them.
 */
final class Tag
{
    /**
     * Describe one tag.
     */
    public function __construct(
        public readonly string $name,
        public readonly string $value = '',
    ) {
    }

    /**
     * Build a tag from a decoded response body.
     *
     * Read back on a scheduled email and on delivery webhooks, so the one public Tag type
     * describes a tag in both directions. Keys this class does not know are dropped; on a webhook
     * the event's raw payload keeps them.
     *
     * @phpstan-param array<string, mixed> $item
     */
    public static function fromArray(array $item): self
    {
        return new self(
            name: Payload::text($item, 'name') ?? '',
            value: Payload::text($item, 'value') ?? '',
        );
    }

    /**
     * Render for the wire.
     *
     * @phpstan-return array<string, string>
     */
    public function toArray(): array
    {
        return ['name' => $this->name, 'value' => $this->value];
    }
}
