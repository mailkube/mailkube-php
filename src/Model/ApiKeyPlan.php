<?php

declare(strict_types=1);

namespace Mailkube\Model;

use Mailkube\Internal\Payload;

/**
 * The organization's plan, its status and its send quotas.
 *
 * `status` is a plain string, never an enum, for the reason given on {@see ApiKeyQuota}. A plan
 * without a daily limit lists the monthly quota only: an absent quota is a missing limit, never a
 * limit of zero.
 */
final class ApiKeyPlan
{
    /**
     * Describe the plan.
     *
     * @phpstan-param list<ApiKeyQuota> $quotas
     */
    public function __construct(
        public readonly string $code = '',
        public readonly string $name = '',
        public readonly string $status = '',
        public readonly array $quotas = [],
    ) {
    }

    /**
     * Create an ApiKeyPlan from a decoded body, ignoring fields this release predates.
     *
     * @phpstan-param array<string, mixed> $body
     */
    public static function fromArray(array $body): self
    {
        return new self(
            code: Payload::text($body, 'code') ?? '',
            name: Payload::text($body, 'name') ?? '',
            status: Payload::text($body, 'status') ?? '',
            quotas: Payload::listOf($body, 'quotas', ApiKeyQuota::fromArray(...)),
        );
    }
}
