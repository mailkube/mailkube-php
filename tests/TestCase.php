<?php

declare(strict_types=1);

namespace Mailkube\Tests;

use Http\Mock\Client as MockHttpClient;
use Mailkube\Client;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase as BaseTestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Log\LoggerInterface;

/**
 * Base for tests that drive the client over an injected PSR-18 double.
 *
 * The client's $httpClient parameter is the dependency-inversion seam described in
 * .rules/SDK_CONTRACT.md; injecting a mock through it is what keeps the whole suite
 * network-free. Nothing here fakes the SDK itself, so the tests exercise the real request
 * building, error mapping and response parsing.
 *
 * Two shapes are available. {@see self::client()} queues one response and is what a single-request
 * verb needs; {@see self::queue()} plus {@see self::clientOverQueue()} drives a multi-request flow
 * such as walking pages, where the responses must be lined up before the first call.
 */
abstract class TestCase extends BaseTestCase
{
    protected const BASE_URL = 'https://api.mailkube.com/mta/v1/';

    protected MockHttpClient $http;

    /**
     * Give each test a fresh mock transport.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->http = new MockHttpClient();
    }

    /**
     * Build a client whose requests all land in {@see self::$http}, queueing one response.
     *
     * @param array<string, string> $headers
     */
    protected function client(int $status = 200, string $body = '{"id":"abc123"}', array $headers = []): Client
    {
        $this->queue($status, $body, $headers);

        return $this->clientOverQueue();
    }

    /**
     * Queue one more response for the mock transport to return, in order.
     *
     * @param array<string, string> $headers
     */
    protected function queue(int $status = 200, string $body = '{"id":"abc123"}', array $headers = []): void
    {
        $this->http->addResponse(new Response($status, $headers, $body));
    }

    /**
     * Build a client over whatever is already queued, adding nothing.
     */
    protected function clientOverQueue(?LoggerInterface $logger = null, ?string $userAgentSuffix = null): Client
    {
        $factory = new Psr17Factory();

        return new Client(
            apiKey: 'mk_test',
            httpClient: $this->http,
            requestFactory: $factory,
            streamFactory: $factory,
            logger: $logger,
            userAgentSuffix: $userAgentSuffix,
        );
    }

    /**
     * Return the single request the client made.
     */
    protected function sentRequest(): RequestInterface
    {
        $requests = $this->sentRequests();
        self::assertCount(1, $requests, 'expected exactly one request');

        return $requests[0];
    }

    /**
     * Return every request the client made, in order.
     *
     * @phpstan-return list<RequestInterface>
     */
    protected function sentRequests(): array
    {
        return array_values($this->http->getRequests());
    }

    /**
     * Return the decoded JSON body of the request the client made.
     *
     * @phpstan-return array<string, mixed>
     */
    protected function sentBody(): array
    {
        /** @var array<string, mixed> $decoded */
        $decoded = json_decode((string) $this->sentRequest()->getBody(), true, 512, JSON_THROW_ON_ERROR);

        return $decoded;
    }
}
