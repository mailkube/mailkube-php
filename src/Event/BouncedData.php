<?php

declare(strict_types=1);

namespace Mailkube\Event;

use Mailkube\Internal\Payload;

/**
 * The data of an `email.bounced` event: a recipient rejected the message.
 */
final class BouncedData extends MessageEventData
{
    public readonly FailureContext $bounce;

    /**
     * Read the shared message fields plus the `bounce` block.
     *
     * @phpstan-param array<string, mixed> $data
     */
    public function __construct(array $data)
    {
        parent::__construct($data);
        $this->bounce = FailureContext::fromArray(Payload::nested($data, 'bounce'));
    }
}
