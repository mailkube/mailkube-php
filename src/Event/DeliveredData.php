<?php

declare(strict_types=1);

namespace Mailkube\Event;

use Mailkube\Internal\Payload;

/**
 * The data of an `email.delivered` event: the receiving server took the message.
 */
final class DeliveredData extends MessageEventData
{
    public readonly DeliveryContext $delivery;

    /**
     * Read the shared message fields plus the `delivery` block.
     *
     * @phpstan-param array<string, mixed> $data
     */
    public function __construct(array $data)
    {
        parent::__construct($data);
        $this->delivery = DeliveryContext::fromArray(Payload::nested($data, 'delivery'));
    }
}
