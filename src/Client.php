<?php

declare(strict_types=1);

namespace Mailkube;

use Mailkube\Exception\MailkubeException;
use Mailkube\Internal\Config;
use Mailkube\Internal\Wiring;
use Mailkube\Resource\EmailsResource;
use Mailkube\Resource\ScheduledEmailsResource;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * The API client, and this package's composition root.
 *
 * Create one and reuse it:
 *
 * ```php
 * $client = new Client();                       // reads MAILKUBE_API_KEY
 * $email = $client->emails->send(
 *     from: 'Acme <hello@yourdomain.com>',
 *     to: 'customer@example.com',
 *     subject: 'Hello world',
 *     html: '<p>It works!</p>',
 * );
 * ```
 *
 * **The HTTP client is injectable.** Leave it null and any PSR-18 implementation you have
 * installed is discovered automatically; pass one to use your own configured client (a Guzzle
 * instance with your proxy and retry middleware, Laravel's container-bound client, or a test
 * double). This is a dependency-inversion seam first and a test seam second: it is what lets the
 * whole suite run without network access.
 *
 * **The timeout applies to a client this SDK creates.** PSR-18 exposes no timeout API, so the value
 * can only reach a concrete implementation: pass no `$httpClient` and the SDK builds a Guzzle or
 * Symfony client carrying `$timeout`. A client you inject is already configured and is used as-is.
 *
 * **Logging is silent by default.** Pass a PSR-3 `$logger`, or set `MAILKUBE_LOG` to a level. See
 * {@see Logging}.
 *
 * There are deliberately **no built-in retries**. A rate-limit error carries its retry-after
 * value and a server error is documented as safe to retry, so the calling application decides.
 * Pass an idempotency key to make a retry safe.
 */
final class Client
{
    /** The ``emails`` namespace. */
    public readonly EmailsResource $emails;

    /** The ``scheduledEmails`` namespace, including ``scheduledEmails->batches``. */
    public readonly ScheduledEmailsResource $scheduledEmails;

    /**
     * Create the client, resolving configuration and wiring the transport.
     *
     * @throws MailkubeException If no API key is provided or found in the environment, or if no
     *                           PSR-18 client / PSR-17 factory is installed and none was passed.
     */
    public function __construct(
        ?string $apiKey = null,
        ?string $baseUrl = null,
        float $timeout = 30.0,
        ?ClientInterface $httpClient = null,
        ?RequestFactoryInterface $requestFactory = null,
        ?StreamFactoryInterface $streamFactory = null,
        ?LoggerInterface $logger = null,
    ) {
        $transport = Wiring::transport(
            new Config($apiKey, $baseUrl, $timeout),
            $httpClient,
            $requestFactory,
            $streamFactory,
            $logger ?? Logging::fromEnvironment() ?? new NullLogger(),
        );

        $this->emails = new EmailsResource($transport);
        $this->scheduledEmails = new ScheduledEmailsResource($transport);
    }
}
