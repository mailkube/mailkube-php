<?php

declare(strict_types=1);

namespace Mailkube\Exception;

/**
 * HTTP 403: Authentication failed or is forbidden, e.g. ``invalid_api_key``.
 */
final class AuthenticationException extends ApiException
{
}
