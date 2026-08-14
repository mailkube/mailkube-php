<?php

declare(strict_types=1);

namespace Mailkube\Event;

use Mailkube\Internal\Payload;

/**
 * A recipient followed a link in the message.
 */
final class EmailClickedEvent extends WebhookEvent
{
    public readonly ClickedData $data;

    /**
     * Read the envelope plus the typed `data` block.
     *
     * @phpstan-param array<string, mixed> $body
     */
    public function __construct(array $body)
    {
        parent::__construct($body);
        $this->data = new ClickedData(Payload::nested($body, 'data'));
    }
}
