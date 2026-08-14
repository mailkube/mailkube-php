<?php

declare(strict_types=1);

namespace Mailkube\Internal;

/**
 * A fully-built request, ready to send.
 *
 * The ``path`` is relative to the client's base URL (e.g. ``emails``), or an absolute URL the
 * API itself issued, such as a pagination link. An absolute URL is only followed when it shares
 * the base URL's origin; see {@see Config::buildUrl()}.
 */
final class RequestSpec
{
    /**
     * Describe one request.
     *
     * @phpstan-param array<string, mixed>|null $body
     * @phpstan-param array<string, string> $query
     * @phpstan-param array<string, string> $headers
     */
    public function __construct(
        public readonly string $path,
        public readonly string $method = 'POST',
        public readonly ?array $body = null,
        public readonly array $query = [],
        public readonly array $headers = [],
    ) {
    }
}
