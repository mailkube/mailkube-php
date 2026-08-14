<?php

declare(strict_types=1);

namespace Mailkube\Model;

use Mailkube\Internal\Payload;

/**
 * The pagination block of a list response.
 *
 * `totalCount` and `currentPage` are **conditional on the wire**: the server omits them when the
 * backing store did not report them, so the defaults here are what a caller actually gets in that
 * case rather than a formality.
 */
final class Pagination
{
    /**
     * Describe one page's position in the range.
     */
    public function __construct(
        public readonly PageSteps $steps = new PageSteps(),
        public readonly int $totalCount = 0,
        public readonly int $currentPage = 1,
    ) {
    }

    /**
     * Create the pagination block from a decoded response body.
     *
     * @phpstan-param array<string, mixed> $body
     */
    public static function fromArray(array $body): self
    {
        return new self(
            steps: PageSteps::fromArray(Payload::nested($body, 'steps')),
            totalCount: Payload::integer($body, 'total_count'),
            currentPage: Payload::integer($body, 'current_page', 1),
        );
    }
}
