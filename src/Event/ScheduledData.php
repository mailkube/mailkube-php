<?php

declare(strict_types=1);

namespace Mailkube\Event;

use Mailkube\Internal\Payload;

/**
 * The data of an `email.scheduled` event: the send was accepted for later delivery.
 */
final class ScheduledData extends MessageEventData
{
    public readonly ScheduledContext $scheduled;

    /**
     * Read the shared message fields plus the `scheduled` block.
     *
     * @phpstan-param array<string, mixed> $data
     */
    public function __construct(array $data)
    {
        parent::__construct($data);
        $this->scheduled = ScheduledContext::fromArray(Payload::nested($data, 'scheduled'));
    }
}
