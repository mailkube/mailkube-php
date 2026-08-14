<?php

declare(strict_types=1);

namespace Mailkube\Tests;

use GuzzleHttp\Client as GuzzleClient;
use Http\Discovery\ClassDiscovery;
use Http\Discovery\Strategy\CommonClassesStrategy;
use Http\Discovery\Strategy\CommonPsr17ClassesStrategy;
use Mailkube\Client;
use Mailkube\Exception\MailkubeException;
use Mailkube\Internal\Discovery;
use PHPUnit\Framework\TestCase as BaseTestCase;

/**
 * Finding a PSR-18 client and PSR-17 factories, and handing the timeout to one we build.
 *
 * These tests deliberately do not inject an $httpClient: injecting one is precisely what skips the
 * code under test.
 */
final class DiscoveryTest extends BaseTestCase
{
    /**
     * Restore the discovery strategies a test disabled, so later tests still find nyholm/psr7.
     */
    protected function tearDown(): void
    {
        ClassDiscovery::setStrategies([CommonClassesStrategy::class, CommonPsr17ClassesStrategy::class]);
        parent::tearDown();
    }

    public function testAMissingImplementationBecomesAnActionableMailkubeError(): void
    {
        ClassDiscovery::setStrategies([]);

        try {
            // No $httpClient and no factories: every seam falls through to discovery, which now
            // has nothing to find. A php-http exception must not escape this SDK's hierarchy.
            new Client(apiKey: 'mk_test');
            self::fail('expected a MailkubeException');
        } catch (MailkubeException $exception) {
            self::assertStringContainsString('composer require', $exception->getMessage());
            self::assertStringContainsString('Mailkube client', $exception->getMessage());
        }
    }

    public function testTheConfiguredTimeoutReachesTheClientTheSdkBuilds(): void
    {
        $client = Discovery::httpClient(7.5);

        // PSR-18 has no timeout API, so the assertion has to reach into the implementation the SDK
        // chose. If this ever fails, the timeout constructor argument has gone dead again.
        self::assertInstanceOf(GuzzleClient::class, $client);
        self::assertSame(7.5, $client->getConfig('timeout'));
        self::assertSame(7.5, $client->getConfig('connect_timeout'));
    }

    public function testTheFactoriesAreDiscoverableInThisInstallation(): void
    {
        // Guards the happy path the previous test disables: with strategies intact, both factories
        // resolve and work, so a consumer with nyholm/psr7 installed needs to pass nothing.
        $request = Discovery::requestFactory()->createRequest('GET', 'https://api.mailkube.com/mta/v1/');
        $stream = Discovery::streamFactory()->createStream('payload');

        self::assertSame('GET', $request->getMethod());
        self::assertSame('payload', (string) $stream);
    }
}
