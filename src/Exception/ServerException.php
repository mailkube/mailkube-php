<?php

declare(strict_types=1);

namespace Mailkube\Exception;

/**
 * HTTP 5xx: An unexpected server error. Safe to retry with backoff.
 */
final class ServerException extends ApiException
{
}
