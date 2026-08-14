<?php

declare(strict_types=1);

namespace Mailkube\Exception;

/**
 * HTTP 409: An idempotency conflict, e.g. ``invalid_idempotent_request``.
 */
final class ConflictException extends ApiException
{
}
