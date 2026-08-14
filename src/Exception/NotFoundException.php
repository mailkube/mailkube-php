<?php

declare(strict_types=1);

namespace Mailkube\Exception;

/**
 * HTTP 404: A referenced resource was not found, e.g. ``template_not_found``.
 */
final class NotFoundException extends ApiException
{
}
