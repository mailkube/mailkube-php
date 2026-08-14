<?php

declare(strict_types=1);

namespace Mailkube\Event;

use Mailkube\Internal\Payload;

/**
 * Any event whose `type` this release does not recognize.
 *
 * Not an error: the server gains event types independently of the SDK, and a receiver that has not
 * upgraded must keep parsing. The payload is reachable untyped through {@see self::$data} and
 * whole through {@see self::$raw}, so nothing is lost, and {@see self::$type} still names it.
 */
final class UnknownEvent extends WebhookEvent
{
    /**
     * The event's `data` block, exactly as decoded.
     *
     * @phpstan-var array<string, mixed>
     */
    public readonly array $data;

    /**
     * Keep the payload verbatim.
     *
     * @phpstan-param array<string, mixed> $body
     */
    public function __construct(array $body)
    {
        parent::__construct($body);
        $this->data = Payload::nested($body, 'data');
    }
}
