<?php

declare(strict_types=1);

namespace Mailkube\Model;

use Mailkube\Internal\Payload;

/**
 * One page of scheduled emails: the rows plus where they sit in the range.
 *
 * Both members default-construct, so a `{}` body yields a valid empty page rather than an error.
 * Use {@see \Mailkube\Resource\ScheduledEmailsResource::iterAll()} to walk every page instead of
 * following {@see self::hasMore()} by hand.
 */
final class ScheduledEmailPage
{
    /**
     * Describe one page.
     *
     * @phpstan-param list<ScheduledEmail> $data
     */
    public function __construct(
        public readonly Pagination $pagination = new Pagination(),
        public readonly array $data = [],
    ) {
    }

    /**
     * Create the page from a decoded response body.
     *
     * @phpstan-param array<string, mixed> $body
     */
    public static function fromArray(array $body): self
    {
        return new self(
            pagination: Pagination::fromArray(Payload::nested($body, 'pagination')),
            data: Payload::listOf($body, 'data', ScheduledEmail::fromArray(...)),
        );
    }

    /**
     * Whether the server offered a link to a following page.
     */
    public function hasMore(): bool
    {
        return $this->pagination->steps->next !== null;
    }
}
