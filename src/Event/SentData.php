<?php

declare(strict_types=1);

namespace Mailkube\Event;

use Mailkube\Internal\Payload;

/**
 * The data of an `email.sent` event: the message was accepted by the sending infrastructure.
 */
final class SentData extends MessageEventData
{
    public readonly DeliveryContext $sent;

    /**
     * Read the shared message fields plus the `sent` block.
     *
     * @phpstan-param array<string, mixed> $data
     */
    public function __construct(array $data)
    {
        parent::__construct($data);
        $this->sent = DeliveryContext::fromArray(Payload::nested($data, 'sent'));
    }
}
