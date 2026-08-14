<?php

declare(strict_types=1);

namespace Mailkube\Event;

use Mailkube\Internal\Payload;

/**
 * The data of an `email.opened` event.
 */
final class OpenedData extends MessageEventData
{
    public readonly EngagementContext $open;

    /**
     * Read the shared message fields plus the `open` block.
     *
     * @phpstan-param array<string, mixed> $data
     */
    public function __construct(array $data)
    {
        parent::__construct($data);
        $this->open = EngagementContext::fromArray(Payload::nested($data, 'open'));
    }
}
