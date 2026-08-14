<?php

declare(strict_types=1);

namespace Mailkube\Event;

use Mailkube\Internal\Payload;

/**
 * A click interaction, carried by `email.clicked`: an open plus the link that was followed.
 */
final class ClickContext extends InteractionContext
{
    /**
     * Describe one click.
     */
    public function __construct(
        string $ipAddress,
        string $userAgent,
        string $timestamp,
        public readonly string $link,
    ) {
        parent::__construct($ipAddress, $userAgent, $timestamp);
    }

    /**
     * Build the block from a decoded event payload.
     *
     * @phpstan-param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(...self::interaction($data), link: Payload::text($data, 'link') ?? '');
    }
}
