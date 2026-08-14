<?php

declare(strict_types=1);

namespace Mailkube\Internal;

use Composer\InstalledVersions;
use Mailkube\Exception\MailkubeException;

/**
 * Resolved client configuration: credentials, base URL, timeout and default headers.
 *
 * This is the transport-agnostic half of the client. It knows nothing about PSR-18 or any HTTP
 * library, which is what lets {@see HttpTransport} be swapped wholesale in tests.
 */
final class Config
{
    public const DEFAULT_BASE_URL = 'https://api.mailkube.com/mta/v1/';

    private const PACKAGE = 'mailkube/mailkube-php';

    public readonly string $apiKey;

    public readonly string $baseUrl;

    /**
     * Resolve configuration from explicit arguments, then the environment, then the defaults.
     *
     * @throws MailkubeException If no API key is provided or found in the environment.
     */
    public function __construct(?string $apiKey = null, ?string $baseUrl = null, public readonly float $timeout = 30.0)
    {
        $resolvedKey = $apiKey ?? self::env('MAILKUBE_API_KEY');
        if ($resolvedKey === null || $resolvedKey === '') {
            throw new MailkubeException(
                'No API key provided. Pass $apiKey or set the MAILKUBE_API_KEY environment variable.'
            );
        }
        $this->apiKey = $resolvedKey;
        $this->baseUrl = $baseUrl ?? self::env('MAILKUBE_BASE_URL') ?? self::DEFAULT_BASE_URL;
    }

    /**
     * Return the auth and non-browser User-Agent headers sent on every request.
     *
     * The version comes from Composer's installed-package metadata, never a literal, so the
     * reported version equals the released version by construction. A hand-maintained constant
     * is how a package ends up reporting a version it is not.
     *
     * @phpstan-return array<string, string>
     */
    public function defaultHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->apiKey,
            'User-Agent' => 'mailkube-php/' . self::version(),
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
    }

    /**
     * Join a relative path onto the base URL, refusing any absolute URL off its origin.
     *
     * Every request carries the Authorization header, so following a link that names a foreign
     * host would hand that host the API key. Enforcing it here rather than in a resource
     * protects every future link-following feature for free.
     *
     * @throws MailkubeException If $path is an absolute URL on a different origin.
     */
    public function buildUrl(string $path): string
    {
        $url = str_contains($path, '://') ? $path : rtrim($this->baseUrl, '/') . '/' . ltrim($path, '/');
        if (self::origin($url) !== self::origin($this->baseUrl)) {
            throw new MailkubeException("Refusing to follow '{$url}': it is not on the configured API origin.");
        }

        return $url;
    }

    /**
     * Return this package's installed version, or a placeholder outside a Composer install.
     *
     * The leading `v` is stripped. Releases are tagged `vX.Y.Z` (`tagFormat` in `.releaserc.json`),
     * and Composer records the tag's pretty form verbatim, so without this the User-Agent would
     * read `mailkube-php/v1.0.0` and break the contract's `mailkube-<lang>/<version>` shape.
     */
    public static function version(): string
    {
        if (!InstalledVersions::isInstalled(self::PACKAGE)) {
            return '0.0.0';
        }

        return ltrim(InstalledVersions::getPrettyVersion(self::PACKAGE) ?? '0.0.0', 'vV');
    }

    /**
     * Read an environment variable, treating an empty value as absent.
     */
    private static function env(string $name): ?string
    {
        $value = getenv($name);

        return ($value === false || $value === '') ? null : $value;
    }

    /**
     * Return the scheme-and-host origin of a URL.
     */
    private static function origin(string $url): string
    {
        $parts = parse_url($url);

        return ($parts['scheme'] ?? '') . '://' . ($parts['host'] ?? '') . ':' . ($parts['port'] ?? '');
    }
}
