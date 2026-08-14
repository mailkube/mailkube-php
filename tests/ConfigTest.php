<?php

declare(strict_types=1);

namespace Mailkube\Tests;

use Mailkube\Exception\MailkubeException;
use Mailkube\Internal\Config;
use PHPUnit\Framework\TestCase as BaseTestCase;

/**
 * Configuration resolution, the User-Agent, and the cross-origin guard.
 */
final class ConfigTest extends BaseTestCase
{
    /**
     * Leave the environment as we found it, whatever a test set.
     */
    protected function tearDown(): void
    {
        putenv('MAILKUBE_API_KEY');
        putenv('MAILKUBE_BASE_URL');
        parent::tearDown();
    }

    public function testAnExplicitKeyWins(): void
    {
        self::assertSame('mk_explicit', (new Config('mk_explicit'))->apiKey);
    }

    public function testTheApiKeyFallsBackToTheEnvironment(): void
    {
        putenv('MAILKUBE_API_KEY=mk_from_env');

        self::assertSame('mk_from_env', (new Config())->apiKey);
    }

    public function testTheBaseUrlFallsBackToTheEnvironmentThenTheDefault(): void
    {
        putenv('MAILKUBE_API_KEY=mk_from_env');
        self::assertSame(Config::DEFAULT_BASE_URL, (new Config())->baseUrl);

        putenv('MAILKUBE_BASE_URL=https://staging.example.com/v1/');
        self::assertSame('https://staging.example.com/v1/', (new Config())->baseUrl);
    }

    public function testAMissingApiKeyRaisesAnActionableError(): void
    {
        $this->expectException(MailkubeException::class);
        $this->expectExceptionMessage('No API key provided');

        new Config();
    }

    public function testTheUserAgentCarriesTheInstalledVersionNotALiteral(): void
    {
        $agent = (new Config('mk_test'))->defaultHeaders()['User-Agent'];

        self::assertStringStartsWith('mailkube-php/', $agent);
        self::assertSame('mailkube-php/' . Config::version(), $agent);
    }

    public function testTheReportedVersionCarriesNoTagPrefix(): void
    {
        // Releases are tagged `vX.Y.Z` and Composer records the tag's pretty form verbatim, so
        // without the strip in Config::version() this reads `mailkube-php/v1.0.0` on every
        // released install. Asserting against Config::version() alone cannot catch that.
        self::assertMatchesRegularExpression('/^\d/', Config::version());
        self::assertStringNotContainsString('/v', (new Config('mk_test'))->defaultHeaders()['User-Agent']);
    }

    public function testARelativePathIsJoinedOntoTheBaseUrl(): void
    {
        self::assertSame(
            Config::DEFAULT_BASE_URL . 'emails',
            (new Config('mk_test'))->buildUrl('emails'),
        );
    }

    public function testAnAbsoluteUrlOnTheSameOriginIsAccepted(): void
    {
        $link = Config::DEFAULT_BASE_URL . 'emails?page=2';

        self::assertSame($link, (new Config('mk_test'))->buildUrl($link));
    }

    public function testALinkOffTheConfiguredOriginIsRefused(): void
    {
        $this->expectException(MailkubeException::class);
        $this->expectExceptionMessage('not on the configured API origin');

        // Every request carries the API key, so a foreign host must never be followed.
        (new Config('mk_test'))->buildUrl('https://evil.example.com/emails');
    }
}
