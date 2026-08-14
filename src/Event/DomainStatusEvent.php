<?php

declare(strict_types=1);

namespace Mailkube\Event;

use Mailkube\Internal\Payload;

/**
 * A sending domain changed state, for example finishing verification or being put on hold.
 */
final class DomainStatusEvent extends WebhookEvent
{
    public readonly DomainStatusData $data;

    /**
     * Read the envelope plus the typed `data` block.
     *
     * @phpstan-param array<string, mixed> $body
     */
    public function __construct(array $body)
    {
        parent::__construct($body);
        $this->data = DomainStatusData::fromArray(Payload::nested($body, 'data'));
    }
}
