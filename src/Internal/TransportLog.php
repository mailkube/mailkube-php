<?php

declare(strict_types=1);

namespace Mailkube\Internal;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * Decides what the SDK's log records look like.
 *
 * Separate from {@see HttpTransport} so that "perform the round trip" and "describe the round trip"
 * can change independently, and so the transport does not carry the PSR-3 and redaction
 * dependencies on top of its own.
 *
 * **No body is ever logged, at any level.** A request body carries recipient addresses, subjects
 * and message content, and a response body echoes enough of it to matter. Headers pass through
 * {@see Redactor} first.
 */
final class TransportLog
{
    /**
     * Bind the recorder to the logger the caller supplied.
     */
    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    /**
     * Record an outbound request, with credentials removed from the headers.
     */
    public function request(RequestInterface $request): void
    {
        /** @phpstan-var array<string, string> $headers */
        $headers = [];
        foreach (array_keys($request->getHeaders()) as $name) {
            $headers[(string) $name] = $request->getHeaderLine((string) $name);
        }

        $this->logger->debug('mailkube request', [
            'method' => $request->getMethod(),
            'url' => (string) $request->getUri(),
            'headers' => Redactor::headers($headers),
        ]);
    }

    /**
     * Record an inbound response.
     */
    public function response(ResponseInterface $response): void
    {
        $this->logger->debug('mailkube response', [
            'status' => $response->getStatusCode(),
            'request_id' => $response->getHeaderLine('X-Request-Id'),
        ]);
    }

    /**
     * Record a transport failure, where no response was received at all.
     */
    public function failure(string $reason): void
    {
        $this->logger->debug('mailkube request failed', ['error' => $reason]);
    }
}
