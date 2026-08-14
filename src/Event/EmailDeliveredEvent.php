<?php

declare(strict_types=1);

namespace Mailkube\Event;

use Mailkube\Internal\Payload;

/**
 * The receiving server accepted the message for a recipient.
 */
final class EmailDeliveredEvent extends WebhookEvent
{
    public readonly DeliveredData $data;

    /**
     * Read the envelope plus the typed `data` block.
     *
     * @phpstan-param array<string, mixed> $body
     */
    public function __construct(array $body)
    {
        parent::__construct($body);
        $this->data = new DeliveredData(Payload::nested($body, 'data'));
    }
}
