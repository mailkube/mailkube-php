<?php

declare(strict_types=1);

namespace Mailkube\Event;

use Mailkube\Internal\Payload;

/**
 * The message was dropped at dispatch and never transmitted. Distinct from `email.bounced`, which means a receiving server rejected it.
 */
final class EmailFailedEvent extends WebhookEvent
{
    public readonly FailedData $data;

    /**
     * Read the envelope plus the typed `data` block.
     *
     * @phpstan-param array<string, mixed> $body
     */
    public function __construct(array $body)
    {
        parent::__construct($body);
        $this->data = new FailedData(Payload::nested($body, 'data'));
    }
}
