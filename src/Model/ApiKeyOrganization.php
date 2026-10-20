<?php

declare(strict_types=1);

namespace Mailkube\Model;

use Mailkube\Internal\Payload;

/**
 * The organization owning an API key's domain.
 */
final class ApiKeyOrganization
{
    /**
     * Describe the organization: its display name and its short alias.
     */
    public function __construct(
        public readonly string $name = '',
        public readonly string $alias = '',
    ) {
    }

    /**
     * Create an ApiKeyOrganization from a decoded body, ignoring fields this release predates.
     *
     * @phpstan-param array<string, mixed> $body
     */
    public static function fromArray(array $body): self
    {
        return new self(
            name: Payload::text($body, 'name') ?? '',
            alias: Payload::text($body, 'alias') ?? '',
        );
    }
}
