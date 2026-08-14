<?php

declare(strict_types=1);

namespace Mailkube\Event;

use Mailkube\Internal\Payload;

/**
 * The data of an `email.clicked` event.
 */
final class ClickedData extends MessageEventData
{
    public readonly ClickContext $click;

    /**
     * Read the shared message fields plus the `click` block.
     *
     * @phpstan-param array<string, mixed> $data
     */
    public function __construct(array $data)
    {
        parent::__construct($data);
        $this->click = ClickContext::fromArray(Payload::nested($data, 'click'));
    }
}
