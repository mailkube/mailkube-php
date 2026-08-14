<?php

declare(strict_types=1);

namespace Mailkube\Event;

use Mailkube\Internal\Payload;

/**
 * A domain's state before the change that produced a `domain.status` event.
 *
 * Both values are server-controlled strings, never enums: a new onboarding state must not turn
 * into a parse error on a client that is already released.
 */
final class DomainStatusPrevious
{
    /**
     * Describe the previous state.
     */
    public function __construct(
        public readonly string $status,
        public readonly string $onboardingState,
    ) {
    }

    /**
     * Build the block from a decoded event payload.
     *
     * @phpstan-param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            status: Payload::text($data, 'status') ?? '',
            onboardingState: Payload::text($data, 'onboarding_state') ?? '',
        );
    }
}
