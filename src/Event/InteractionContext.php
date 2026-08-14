<?php

declare(strict_types=1);

namespace Mailkube\Event;

use Mailkube\Internal\Payload;

/**
 * The fields an open and a click share.
 *
 * Abstract and never instantiated. It exists so `ipAddress`/`userAgent`/`timestamp` and the
 * camelCase wire keys they come from are written once: {@see EngagementContext} is the open,
 * {@see ClickContext} is the same plus the link that was followed.
 */
abstract class InteractionContext
{
    /**
     * Describe one interaction.
     */
    public function __construct(
        public readonly string $ipAddress,
        public readonly string $userAgent,
        public readonly string $timestamp,
    ) {
    }

    /**
     * Read the shared interaction fields as named arguments.
     *
     * These three keys are camelCase on the wire, unlike every other block in the catalogue.
     *
     * @phpstan-param array<string, mixed> $data
     *
     * @phpstan-return array{ipAddress: string, userAgent: string, timestamp: string}
     */
    protected static function interaction(array $data): array
    {
        return [
            'ipAddress' => Payload::text($data, 'ipAddress') ?? '',
            'userAgent' => Payload::text($data, 'userAgent') ?? '',
            'timestamp' => Payload::text($data, 'timestamp') ?? '',
        ];
    }
}
