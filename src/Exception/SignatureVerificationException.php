<?php

declare(strict_types=1);

namespace Mailkube\Exception;

/**
 * A webhook signature could not be verified (bad signature, stale, or malformed headers).
 */
final class SignatureVerificationException extends MailkubeException
{
}
