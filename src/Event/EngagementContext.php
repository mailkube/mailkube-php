<?php

declare(strict_types=1);

namespace Mailkube\Event;

/**
 * An open interaction, carried by `email.opened`.
 */
final class EngagementContext extends InteractionContext
{
    /**
     * Build the block from a decoded event payload.
     *
     * @phpstan-param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(...self::interaction($data));
    }
}
