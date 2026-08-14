<?php

declare(strict_types=1);

namespace Mailkube\Tests;

use Mailkube\Exception\MailkubeException;
use Mailkube\Internal\Config;
use PHPUnit\Framework\Attributes\DataProvider;
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

    public function testTheReportedVersionIsNeverAPrefixedOrDerivedString(): void
    {
        // Whatever the checkout, what reaches the wire looks like a version. Asserting against
        // Config::version() alone cannot catch a `v` prefix, so assert the shape.
        self::assertMatchesRegularExpression('/^\d/', Config::version());
        self::assertStringNotContainsString('/v', (new Config('mk_test'))->defaultHeaders()['User-Agent']);
    }

    /**
     * Every pretty version Composer can hand us, and what the User-Agent must report for it.
     *
     * The ambient assertions above cannot cover this: the suite only ever runs from one kind of
     * checkout, so the case that actually shipped broken is the one it never reaches.
     *
     * @phpstan-return array<string, array{string, string}>
     */
    public static function prettyVersions(): array
    {
        return [
            'a release tag keeps its version' => ['1.4.2', '1.4.2'],
            'the tag prefix is stripped' => ['v1.4.2', '1.4.2'],
            'a pre-release is a real version' => ['v2.0.0-rc.1', '2.0.0-rc.1'],
            'a branch checkout is not a version' => ['dev-main', '0.0.0'],
            'nor is any other branch' => ['dev-feature/scheduled', '0.0.0'],
            'nor is a git-less install' => ['1.0.0+no-version-set', '0.0.0'],
            'nor is nothing at all' => ['', '0.0.0'],
        ];
    }

    #[DataProvider('prettyVersions')]
    public function testANonReleaseVersionDegradesToThePlaceholder(string $pretty, string $expected): void
    {
        self::assertSame($expected, Config::normalizeVersion($pretty));
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
