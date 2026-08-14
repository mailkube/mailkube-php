<?php

declare(strict_types=1);

namespace Mailkube\Event;

use Mailkube\Internal\Payload;

/**
 * Recipients were dropped before sending because they are on the suppression list.
 */
final class EmailSuppressedEvent extends WebhookEvent
{
    public readonly SuppressedData $data;

    /**
     * Read the envelope plus the typed `data` block.
     *
     * @phpstan-param array<string, mixed> $body
     */
    public function __construct(array $body)
    {
        parent::__construct($body);
        $this->data = new SuppressedData(Payload::nested($body, 'data'));
    }
}
