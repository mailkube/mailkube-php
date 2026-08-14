<?php

declare(strict_types=1);

namespace Mailkube\Exception;

/**
 * A transport-level failure (connection error or timeout) with no HTTP response.
 *
 * Deliberately not an {@see ApiException}: there is no status code and no server envelope to
 * report, so callers that branch on API semantics must not catch this by accident.
 */
final class ConnectionException extends MailkubeException
{
}
