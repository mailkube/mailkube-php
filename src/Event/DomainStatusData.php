<?php

declare(strict_types=1);

namespace Mailkube\Event;

use Mailkube\Internal\Payload;

/**
 * The data of a `domain.status` event: a sending domain changed state.
 *
 * Carries no message context: the event is about the domain, not about one message. `status` and
 * `onboardingState` are server-controlled strings, never enums.
 */
final class DomainStatusData
{
    /**
     * Describe the domain's new state and the state it left.
     */
    public function __construct(
        public readonly string $domain,
        public readonly string $status,
        public readonly string $onboardingState,
        public readonly DomainStatusPrevious $previous,
    ) {
    }

    /**
     * Build the data block from a decoded event payload.
     *
     * @phpstan-param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            domain: Payload::text($data, 'domain') ?? '',
            status: Payload::text($data, 'status') ?? '',
            onboardingState: Payload::text($data, 'onboarding_state') ?? '',
            previous: DomainStatusPrevious::fromArray(Payload::nested($data, 'previous')),
        );
    }
}
