<?php

declare(strict_types=1);

namespace Mailkube\Event;

use Mailkube\Internal\Payload;

/**
 * A webhook endpoint was enabled, disabled or deleted.
 *
 * Worth handling: this is how a receiver learns its own endpoint was auto-disabled after repeated
 * delivery failures, which is otherwise silent.
 */
final class WebhookStatusEvent extends WebhookEvent
{
    public readonly WebhookStatusData $data;

    /**
     * Read the envelope plus the typed `data` block.
     *
     * @phpstan-param array<string, mixed> $body
     */
    public function __construct(array $body)
    {
        parent::__construct($body);
        $this->data = WebhookStatusData::fromArray(Payload::nested($body, 'data'));
    }
}
