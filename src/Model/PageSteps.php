<?php

declare(strict_types=1);

namespace Mailkube\Model;

use Mailkube\Internal\Payload;

/**
 * The links to the pages either side of the current one.
 *
 * The API **omits** a step at the ends of the range rather than sending null, so absent and null
 * mean the same thing here and both default to null.
 *
 * The links are absolute URLs the API issued. They are only ever followed when they share the
 * configured base URL's origin: every request carries the Authorization header, so a link naming a
 * foreign host would hand that host the API key. {@see \Mailkube\Internal\Config::buildUrl()}
 * enforces that centrally.
 */
final class PageSteps
{
    /**
     * Describe the surrounding pages.
     */
    public function __construct(
        public readonly ?string $next = null,
        public readonly ?string $previous = null,
    ) {
    }

    /**
     * Create the steps from a decoded pagination block.
     *
     * @phpstan-param array<string, mixed> $body
     */
    public static function fromArray(array $body): self
    {
        return new self(
            next: Payload::text($body, 'next'),
            previous: Payload::text($body, 'previous'),
        );
    }
}
