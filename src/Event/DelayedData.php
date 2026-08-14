<?php

declare(strict_types=1);

namespace Mailkube\Event;

use Mailkube\Internal\Payload;

/**
 * The data of an `email.delivery_delayed` event: delivery was deferred and will be retried.
 */
final class DelayedData extends MessageEventData
{
    public readonly FailureContext $delay;

    /**
     * Read the shared message fields plus the `delay` block.
     *
     * @phpstan-param array<string, mixed> $data
     */
    public function __construct(array $data)
    {
        parent::__construct($data);
        $this->delay = FailureContext::fromArray(Payload::nested($data, 'delay'));
    }
}
