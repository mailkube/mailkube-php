<?php

declare(strict_types=1);

namespace Mailkube\Exception;

/**
 * HTTP 400: The request envelope was invalid, e.g. ``missing_user_agent``.
 */
final class BadRequestException extends ApiException
{
}
