<?php

declare(strict_types=1);

namespace Mailkube\Internal;

use Http\Discovery\Exception as DiscoveryException;
use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;
use Mailkube\Exception\MailkubeException;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Finds the PSR-18 client and PSR-17 factories this package deliberately does not ship.
 *
 * Two things happen here that cannot happen anywhere else:
 *
 * - **A discovery failure becomes this SDK's own exception.** `php-http/discovery` throwing at a
 *   consumer who simply has not installed a client names neither this package nor the fix, so the
 *   message is rewritten to name both.
 * - **The configured timeout is applied to a client the SDK creates itself.** PSR-18 has no timeout
 *   API, so the value can only reach a concrete implementation. A client the caller injects never
 *   arrives here: it is already configured, and is used untouched.
 */
final class Discovery
{
    /** PSR-18 implementations this SDK knows how to construct with a timeout, in preference order. */
    private const CONFIGURABLE = [
        'GuzzleHttp\Client' => true,
        'Symfony\Component\HttpClient\Psr18Client' => false,
    ];

    /** Symfony transports that accept default options, in preference order. */
    private const SYMFONY_TRANSPORTS = [
        'Symfony\Component\HttpClient\CurlHttpClient',
        'Symfony\Component\HttpClient\NativeHttpClient',
    ];

    /**
     * Return a PSR-18 client, preferring one this SDK can hand the configured timeout to.
     *
     * @throws MailkubeException If no PSR-18 implementation is installed.
     */
    public static function httpClient(float $timeout): ClientInterface
    {
        $configured = self::configuredClient($timeout);
        if ($configured !== null) {
            return $configured;
        }

        // Nothing this SDK knows how to configure: fall back to whatever is installed. Such a
        // client keeps its own timeout, which the README documents.
        return self::find(
            static fn (): ClientInterface => Psr18ClientDiscovery::find(),
            'PSR-18 HTTP client',
            'guzzlehttp/guzzle',
            'httpClient',
        );
    }

    /**
     * Return a PSR-17 request factory.
     *
     * @throws MailkubeException If no PSR-17 implementation is installed.
     */
    public static function requestFactory(): RequestFactoryInterface
    {
        return self::find(
            static fn (): RequestFactoryInterface => Psr17FactoryDiscovery::findRequestFactory(),
            'PSR-17 request factory',
            'nyholm/psr7',
            'requestFactory',
        );
    }

    /**
     * Return a PSR-17 stream factory.
     *
     * @throws MailkubeException If no PSR-17 implementation is installed.
     */
    public static function streamFactory(): StreamFactoryInterface
    {
        return self::find(
            static fn (): StreamFactoryInterface => Psr17FactoryDiscovery::findStreamFactory(),
            'PSR-17 stream factory',
            'nyholm/psr7',
            'streamFactory',
        );
    }

    /**
     * Construct a known PSR-18 implementation with the timeout applied, or null if none is installed.
     */
    private static function configuredClient(float $timeout): ?ClientInterface
    {
        foreach (self::CONFIGURABLE as $class => $isGuzzle) {
            if (!class_exists($class)) {
                continue;
            }
            $client = $isGuzzle ? self::guzzle($class, $timeout) : self::symfony($class, $timeout);
            if ($client instanceof ClientInterface) {
                return $client;
            }
        }

        return null;
    }

    /**
     * Instantiate Guzzle with the timeout applied to both the connect and the total budget.
     *
     * @phpstan-param class-string $class
     */
    private static function guzzle(string $class, float $timeout): object
    {
        return new $class(['timeout' => $timeout, 'connect_timeout' => $timeout]);
    }

    /**
     * Instantiate Symfony's PSR-18 adapter over a transport carrying the timeout, if one exists.
     *
     * `HttpClient::create()` is deliberately not used: calling a static method on a class name held
     * in a variable defeats static analysis, and naming the transports directly is just as precise.
     *
     * @phpstan-param class-string $class
     */
    private static function symfony(string $class, float $timeout): ?object
    {
        $options = ['timeout' => $timeout, 'max_duration' => $timeout];
        foreach (self::SYMFONY_TRANSPORTS as $transport) {
            if (class_exists($transport)) {
                return new $class(new $transport($options));
            }
        }

        return null;
    }

    /**
     * Run one discovery call, translating its failure into an actionable MailkubeException.
     *
     * @phpstan-template T of object
     *
     * @phpstan-param callable(): T $discover
     *
     * @phpstan-return T
     *
     * @throws MailkubeException If discovery fails.
     */
    private static function find(callable $discover, string $what, string $package, string $argument): object
    {
        try {
            return $discover();
        } catch (DiscoveryException $exception) {
            throw new MailkubeException(
                "No {$what} found. Install one (for example `composer require {$package}`) "
                . "or pass an explicit \${$argument} to the Mailkube client.",
                0,
                $exception,
            );
        }
    }
}
