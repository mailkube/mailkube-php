<?php

declare(strict_types=1);

namespace Mailkube\Event;

use Mailkube\Internal\Payload;

/**
 * The registry of every event type this release understands.
 *
 * {@see self::MAP} is **both** the parse table and the known-type set. That is the whole point: a
 * hand-maintained second list of known types would let a new event be half-registered, routing it
 * to {@see UnknownEvent} at runtime with no test failing. Adding an event is adding one row here
 * and one class.
 */
final class EventCatalogue
{
    /**
     * Wire `type` to the class that models it.
     *
     * @phpstan-var array<string, class-string<WebhookEvent>>
     */
    public const MAP = [
        'email.sent' => EmailSentEvent::class,
        'email.delivered' => EmailDeliveredEvent::class,
        'email.bounced' => EmailBouncedEvent::class,
        'email.delivery_delayed' => EmailDeliveryDelayedEvent::class,
        'email.suppressed' => EmailSuppressedEvent::class,
        'email.scheduled' => EmailScheduledEvent::class,
        'email.failed' => EmailFailedEvent::class,
        'email.opened' => EmailOpenedEvent::class,
        'email.clicked' => EmailClickedEvent::class,
        'domain.status' => DomainStatusEvent::class,
        'webhook.status' => WebhookStatusEvent::class,
    ];

    /**
     * Build the event a decoded payload describes, or an UnknownEvent for a type we do not model.
     *
     * @phpstan-param array<string, mixed> $body
     */
    public static function build(array $body): WebhookEvent
    {
        $class = self::MAP[Payload::text($body, 'type') ?? ''] ?? UnknownEvent::class;

        return new $class($body);
    }
}
