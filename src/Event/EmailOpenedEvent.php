<?php

declare(strict_types=1);

namespace Mailkube\Event;

use Mailkube\Internal\Payload;

/**
 * A recipient opened the message.
 */
final class EmailOpenedEvent extends WebhookEvent
{
    public readonly OpenedData $data;

    /**
     * Read the envelope plus the typed `data` block.
     *
     * @phpstan-param array<string, mixed> $body
     */
    public function __construct(array $body)
    {
        parent::__construct($body);
        $this->data = new OpenedData(Payload::nested($body, 'data'));
    }
}
