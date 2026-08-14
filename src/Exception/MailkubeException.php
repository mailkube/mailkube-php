<?php

declare(strict_types=1);

namespace Mailkube\Exception;

use RuntimeException;

/**
 * Base class for every error this SDK raises.
 *
 * Catching this one type catches everything the SDK can throw, including configuration
 * problems, transport failures and API errors.
 */
class MailkubeException extends RuntimeException
{
}
