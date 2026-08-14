<?php

declare(strict_types=1);

namespace Mailkube\Internal;

use Mailkube\Contract\SendTransport;
use Mailkube\Contract\TypedTransport;
use Mailkube\Exception\ConnectionException;
use Mailkube\Exception\MailkubeException;
use Mailkube\Model\Email;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * The one place this SDK performs I/O.
 *
 * Everything PSR-18-aware lives here; no resource and no model imports an HTTP interface. The
 * request shaping and the error mapping are delegated, so this class is only the round trip.
 *
 * It is also the only place that logs. Records carry the method, URL, redacted headers, status and
 * request id, and never a body: request and response bodies hold recipient addresses, subjects and
 * message content.
 */
final class HttpTransport implements SendTransport, TypedTransport
{
    /**
     * Bind the transport to the PSR-18 client it drives and its three collaborators.
     */
    public function __construct(
        private readonly ClientInterface $httpClient,
        private readonly RequestBuilder $builder,
        private readonly ResponseHandler $handler,
        private readonly TransportLog $log,
    ) {
    }

    /**
     * Send an email and build the accepted-send result.
     *
     * @throws ConnectionException On a transport failure or timeout.
     * @throws MailkubeException On a 2xx response whose body is not a send acknowledgement.
     */
    public function sendEmail(RequestSpec $spec): Email
    {
        $response = $this->roundTrip($spec);

        $body = $this->handler->okBody($response);
        if (!isset($body['id'])) {
            throw new MailkubeException(
                "Expected a JSON body with an 'id' from a {$response->getStatusCode()} response."
            );
        }

        $replayed = strtolower($response->getHeaderLine('Idempotent-Replayed')) === 'true';

        return Email::fromArray($body, $replayed);
    }

    /**
     * Perform a request and hydrate the decoded 2xx body through $model.
     *
     * @phpstan-template T of object
     *
     * @phpstan-param callable(array<string, mixed>): T $model
     *
     * @phpstan-return T
     *
     * @throws ConnectionException On a transport failure or timeout.
     * @throws MailkubeException On a 2xx response whose body is not a JSON object.
     */
    public function request(RequestSpec $spec, callable $model): object
    {
        return $model($this->handler->okObject($this->roundTrip($spec)));
    }

    /**
     * Perform one request and return the raw response, logging both ends.
     *
     * Every verb funnels through here, so a new verb inherits the connection-error translation and
     * the log records without opting in.
     *
     * @throws ConnectionException On a transport failure or timeout.
     */
    private function roundTrip(RequestSpec $spec): ResponseInterface
    {
        $request = $this->builder->build($spec);
        $this->log->request($request);

        try {
            $response = $this->httpClient->sendRequest($request);
        } catch (ClientExceptionInterface $exception) {
            $this->log->failure($exception->getMessage());

            throw new ConnectionException($exception->getMessage(), 0, $exception);
        }

        $this->log->response($response);

        return $response;
    }
}
