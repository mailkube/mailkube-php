<?php

declare(strict_types=1);

namespace Mailkube\Model;

use Mailkube\Internal\Payload;

/**
 * One send quota of the plan: its limit and what the current period has used.
 *
 * `name` (`emails_per_day`, `emails_per_month`) is a plain string, never an enum: a closed set
 * would turn a new server value into a parse error on a client that is already released. Figures
 * count recipients, and `period` is the UTC day (`2026-10-19`) or month (`2026-10`) they cover.
 */
final class ApiKeyQuota
{
    /**
     * Describe one quota.
     */
    public function __construct(
        public readonly string $name = '',
        public readonly int $limit = 0,
        public readonly int $used = 0,
        public readonly int $remaining = 0,
        public readonly string $period = '',
    ) {
    }

    /**
     * Create an ApiKeyQuota from a decoded body, ignoring fields this release predates.
     *
     * @phpstan-param array<string, mixed> $body
     */
    public static function fromArray(array $body): self
    {
        return new self(
            name: Payload::text($body, 'name') ?? '',
            limit: Payload::integer($body, 'limit'),
            used: Payload::integer($body, 'used'),
            remaining: Payload::integer($body, 'remaining'),
            period: Payload::text($body, 'period') ?? '',
        );
    }
}
