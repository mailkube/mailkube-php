<?php

declare(strict_types=1);

namespace Mailkube\Internal;

use Mailkube\Exception\MailkubeException;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Assembles the transport from what the caller supplied and what {@see Discovery} can find.
 *
 * Split out of {@see \Mailkube\Client} so the composition root stays a list of resources rather
 * than a pile of collaborators: `Client` names its resources and this class, which is what keeps it
 * under phpmd's coupling limit as resources are added. Finding an implementation is
 * {@see Discovery}'s job; this class only decides what gets built from what.
 */
final class Wiring
{
    /**
     * Build the transport, discovering anything the caller left null.
     *
     * @throws MailkubeException If a required PSR-18 client or PSR-17 factory cannot be found.
     */
    public static function transport(
        Config $config,
        ?ClientInterface $httpClient,
        ?RequestFactoryInterface $requestFactory,
        ?StreamFactoryInterface $streamFactory,
        LoggerInterface $logger,
    ): HttpTransport {
        $builder = new RequestBuilder(
            $config,
            $requestFactory ?? Discovery::requestFactory(),
            $streamFactory ?? Discovery::streamFactory(),
        );

        return new HttpTransport(
            $httpClient ?? Discovery::httpClient($config->timeout),
            $builder,
            new ResponseHandler(),
            new TransportLog($logger),
        );
    }
}
