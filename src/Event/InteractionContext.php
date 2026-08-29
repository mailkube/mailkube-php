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
     *
     * `$ipAddress`, `$country` and `$userAgent` are recorded only where the sending domain elected
     * them, which is off by default. `$ipAddress` and `$userAgent` read as empty strings when the
     * sender declined, because they predate the election and stayed non-nullable rather than
     * breaking every caller; `$country` is nullable, so on that field alone an unelected value is
     * distinguishable from a blank one.
     */
    public function __construct(
        public readonly string $ipAddress,
        public readonly string $userAgent,
        public readonly string $timestamp,
        public readonly ?string $country = null,
    ) {
    }

    /**
     * Read the shared interaction fields as named arguments.
     *
     * `ipAddress`, `userAgent` and `country` are camelCase on the wire, unlike every other block in
     * the catalogue, and are absent entirely where the sending domain did not elect them.
     *
     * @phpstan-param array<string, mixed> $data
     *
     * @phpstan-return array{ipAddress: string, userAgent: string, timestamp: string, country: string|null}
     */
    protected static function interaction(array $data): array
    {
        return [
            'ipAddress' => Payload::text($data, 'ipAddress') ?? '',
            'userAgent' => Payload::text($data, 'userAgent') ?? '',
            'timestamp' => Payload::text($data, 'timestamp') ?? '',
            'country' => Payload::text($data, 'country'),
        ];
    }
}
