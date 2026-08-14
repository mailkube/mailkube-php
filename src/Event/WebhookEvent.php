<?php

declare(strict_types=1);

namespace Mailkube\Event;

use Mailkube\Internal\Payload;

/**
 * The base of every inbound webhook event.
 *
 * Concrete subclasses are what make an event usable without string comparisons:
 *
 * ```php
 * $event = Webhooks::verify($raw, $headers, $secret);
 *
 * if ($event instanceof EmailBouncedEvent) {
 *     $event->data->bounce->reason;   // typed, and checked by static analysis
 * }
 * ```
 *
 * Python expresses this as a discriminated union of models; PHP expresses it as a sealed-by-
 * convention hierarchy, because `instanceof` narrowing is what gives a caller the same guarantee.
 * The translation is recorded in `.rules/SDK_DESIGN.md`.
 *
 * Two rules are inverted relative to response models, both so a released client never raises on a
 * payload it has not seen:
 *
 * - **Unknown fields are preserved, not dropped.** {@see self::$raw} is the whole decoded body, so
 *   a receiver that logs or forwards an event keeps fields this version predates.
 * - **An unknown event type is a valid parse result.** It becomes an {@see UnknownEvent} rather
 *   than an error, so a new server-side event never forces an SDK upgrade on receivers.
 */
abstract class WebhookEvent
{
    /** The event type, e.g. `email.delivered`. */
    public readonly string $type;

    /** When the event was produced, as the verbatim ISO-8601 string the server sent. */
    public readonly string $createdAt;

    /**
     * The whole decoded payload, including any field this release does not model.
     *
     * @phpstan-var array<string, mixed>
     */
    public readonly array $raw;

    /**
     * Read the envelope fields shared by every event.
     *
     * @phpstan-param array<string, mixed> $body
     */
    public function __construct(array $body)
    {
        $this->type = Payload::text($body, 'type') ?? '';
        $this->createdAt = Payload::text($body, 'created_at') ?? '';
        $this->raw = $body;
    }
}
