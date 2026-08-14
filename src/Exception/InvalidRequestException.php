<?php

declare(strict_types=1);

namespace Mailkube\Exception;

/**
 * HTTP 422: The request was rejected by a send-policy check, e.g. ``validation_error``.
 */
final class InvalidRequestException extends ApiException
{
}
