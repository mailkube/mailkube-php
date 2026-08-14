<?php

declare(strict_types=1);

namespace Mailkube\Event;

use Mailkube\Internal\Payload;

/**
 * The sending infrastructure accepted the message. Delivery to the recipient is not confirmed yet: wait for `email.delivered` for that.
 */
final class EmailSentEvent extends WebhookEvent
{
    public readonly SentData $data;

    /**
     * Read the envelope plus the typed `data` block.
     *
     * @phpstan-param array<string, mixed> $body
     */
    public function __construct(array $body)
    {
        parent::__construct($body);
        $this->data = new SentData(Payload::nested($body, 'data'));
    }
}
