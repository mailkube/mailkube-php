<?php

declare(strict_types=1);

namespace Mailkube\Exception;

/**
 * HTTP 429: The rate limit was exceeded. Inspect ``retryAfter``.
 */
final class RateLimitException extends ApiException
{
}
