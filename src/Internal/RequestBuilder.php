<?php

declare(strict_types=1);

namespace Mailkube\Internal;

use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Turns a {@see RequestSpec} into a PSR-7 request.
 *
 * Split out from the transport so that "how a request is shaped" and "how a request is sent" can
 * be read, tested and changed independently.
 */
final class RequestBuilder
{
    /**
     * Bind the builder to its configuration and the PSR-17 factories it uses.
     */
    public function __construct(
        private readonly Config $config,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly StreamFactoryInterface $streamFactory,
    ) {
    }

    /**
     * Build the absolute, fully-headed PSR-7 request for a spec.
     */
    public function build(RequestSpec $spec): RequestInterface
    {
        $url = $this->config->buildUrl($spec->path);
        if ($spec->query !== []) {
            $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($spec->query);
        }

        $request = $this->requestFactory->createRequest($spec->method, $url);
        foreach ($this->config->defaultHeaders() as $name => $value) {
            $request = $request->withHeader($name, $value);
        }
        foreach ($spec->headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        if ($spec->body !== null) {
            $request = $request->withBody($this->streamFactory->createStream(Serializer::toJson($spec->body)));
        }

        return $request;
    }
}
