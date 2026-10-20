<?php

declare(strict_types=1);

namespace Mailkube\Resource;

use Mailkube\Contract\TypedTransport;
use Mailkube\Internal\RequestSpec;
use Mailkube\Model\ApiKey;

/**
 * The client's own credential, reached as `$client->credential`.
 *
 * It describes the API key the client was constructed with and holds no setter: a client takes its
 * key at construction and keeps it, which is what makes one instance safe to share. A second key is
 * a second client.
 */
final class CredentialResource
{
    /** The route on which the calling key describes itself, relative to the configured base URL. */
    private const INFO_PATH = 'api-keys/me';

    /**
     * Bind the resource to the transport performing its requests.
     */
    public function __construct(private readonly TypedTransport $transport)
    {
    }

    /**
     * Describe the API key this client authenticates with: its domain, organization, source-IP
     * allowlist, plan and send-quota usage.
     *
     * The key is checked exactly as a send checks it, so a key that cannot send raises an
     * {@see \Mailkube\Exception\AuthenticationException} (`invalid_api_key`), which also makes this
     * the way to verify a key. The call counts as a use of the key. Plan and quota figures can be up
     * to 30 seconds old.
     */
    public function info(): ApiKey
    {
        return $this->transport->request(self::infoSpec(), ApiKey::fromArray(...));
    }

    /**
     * Build the request describing the calling key.
     */
    private static function infoSpec(): RequestSpec
    {
        return new RequestSpec(path: self::INFO_PATH, method: 'GET');
    }
}
