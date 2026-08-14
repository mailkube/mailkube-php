<?php

declare(strict_types=1);

namespace Mailkube\Event;

use Mailkube\Internal\Payload;

/**
 * The data of an `email.failed` event: the message was dropped at dispatch and never sent.
 */
final class FailedData extends MessageEventData
{
    public readonly SendFailureContext $failed;

    /**
     * Read the shared message fields plus the `failed` block.
     *
     * @phpstan-param array<string, mixed> $data
     */
    public function __construct(array $data)
    {
        parent::__construct($data);
        $this->failed = SendFailureContext::fromArray(Payload::nested($data, 'failed'));
    }
}
