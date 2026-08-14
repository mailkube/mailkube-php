<?php

declare(strict_types=1);

namespace Mailkube\Event;

use Mailkube\Internal\Payload;

/**
 * Delivery to a recipient was deferred; the platform will retry.
 */
final class EmailDeliveryDelayedEvent extends WebhookEvent
{
    public readonly DelayedData $data;

    /**
     * Read the envelope plus the typed `data` block.
     *
     * @phpstan-param array<string, mixed> $body
     */
    public function __construct(array $body)
    {
        parent::__construct($body);
        $this->data = new DelayedData(Payload::nested($body, 'data'));
    }
}
