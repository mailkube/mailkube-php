<?php

declare(strict_types=1);

namespace Mailkube\Event;

use Mailkube\Internal\Payload;

/**
 * A send was accepted for later delivery and now lives in the scheduled collection.
 */
final class EmailScheduledEvent extends WebhookEvent
{
    public readonly ScheduledData $data;

    /**
     * Read the envelope plus the typed `data` block.
     *
     * @phpstan-param array<string, mixed> $body
     */
    public function __construct(array $body)
    {
        parent::__construct($body);
        $this->data = new ScheduledData(Payload::nested($body, 'data'));
    }
}
