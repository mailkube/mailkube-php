<?php

declare(strict_types=1);

namespace Mailkube\Model;

use Mailkube\Internal\Payload;

/**
 * The API key the client authenticates with, as it describes itself.
 *
 * Returned by `$client->credential->info()`. Readonly, and unknown wire fields are ignored.
 * Timestamps stay the verbatim ISO-8601 strings the server sent. Plan and quota figures can be up
 * to 30 seconds old. `allowedSourceIps` lists CIDR networks, and an empty list means any address.
 * `plan` is null when the organization has no subscription.
 */
final class ApiKey
{
    /**
     * Describe the calling key.
     *
     * @phpstan-param list<string> $allowedSourceIps
     */
    public function __construct(
        public readonly string $keyId,
        public readonly string $object = 'api_key',
        public readonly string $name = '',
        public readonly ?string $description = null,
        public readonly string $createdAt = '',
        public readonly ?string $lastAuthAt = null,
        public readonly array $allowedSourceIps = [],
        public readonly ApiKeyDomain $domain = new ApiKeyDomain(),
        public readonly ApiKeyOrganization $organization = new ApiKeyOrganization(),
        public readonly ?ApiKeyPlan $plan = null,
    ) {
    }

    /**
     * Create an ApiKey from a decoded response body, ignoring fields this release predates.
     *
     * `plan` is the one field the API sends as null, meaning "no subscription", so it is read only
     * when an object is there: decoding null as an empty plan would build one that looks real.
     *
     * @phpstan-param array<string, mixed> $body
     */
    public static function fromArray(array $body): self
    {
        return new self(
            keyId: Payload::text($body, 'key_id') ?? '',
            object: Payload::text($body, 'object') ?? 'api_key',
            name: Payload::text($body, 'name') ?? '',
            description: Payload::text($body, 'description'),
            createdAt: Payload::text($body, 'created_at') ?? '',
            lastAuthAt: Payload::text($body, 'last_auth_at'),
            allowedSourceIps: Payload::strings($body, 'allowed_source_ips'),
            domain: ApiKeyDomain::fromArray(Payload::nested($body, 'domain')),
            organization: ApiKeyOrganization::fromArray(Payload::nested($body, 'organization')),
            plan: isset($body['plan']) && is_array($body['plan'])
                ? ApiKeyPlan::fromArray(Payload::nested($body, 'plan'))
                : null,
        );
    }
}
