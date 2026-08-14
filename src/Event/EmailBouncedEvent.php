<?php

declare(strict_types=1);

namespace Mailkube\Event;

use Mailkube\Internal\Payload;

/**
 * A recipient rejected the message. Bounces feed the suppression list.
 */
final class EmailBouncedEvent extends WebhookEvent
{
    public readonly BouncedData $data;

    /**
     * Read the envelope plus the typed `data` block.
     *
     * @phpstan-param array<string, mixed> $body
     */
    public function __construct(array $body)
    {
        parent::__construct($body);
        $this->data = new BouncedData(Payload::nested($body, 'data'));
    }
}
