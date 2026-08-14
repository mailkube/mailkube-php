<?php

declare(strict_types=1);

namespace Mailkube\Event;

use Mailkube\Internal\Payload;

/**
 * The data of an `email.suppressed` event: recipients were dropped by the suppression list.
 */
final class SuppressedData extends MessageEventData
{
    public readonly SuppressionContext $suppression;

    /**
     * Read the shared message fields plus the `suppression` block.
     *
     * @phpstan-param array<string, mixed> $data
     */
    public function __construct(array $data)
    {
        parent::__construct($data);
        $this->suppression = SuppressionContext::fromArray(Payload::nested($data, 'suppression'));
    }
}
