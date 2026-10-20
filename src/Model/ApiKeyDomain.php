<?php

declare(strict_types=1);

namespace Mailkube\Model;

use Mailkube\Internal\Payload;

/**
 * The sending domain an API key belongs to.
 */
final class ApiKeyDomain
{
    /**
     * Describe the domain.
     */
    public function __construct(public readonly string $name = '')
    {
    }

    /**
     * Create an ApiKeyDomain from a decoded body, ignoring fields this release predates.
     *
     * @phpstan-param array<string, mixed> $body
     */
    public static function fromArray(array $body): self
    {
        return new self(name: Payload::text($body, 'name') ?? '');
    }
}
