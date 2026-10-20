<?php

declare(strict_types=1);

namespace Mailkube\Tests;

use Mailkube\ErrorName;
use Mailkube\Exception\ApiException;
use Mailkube\Exception\AuthenticationException;
use Mailkube\Exception\MailkubeException;
use Mailkube\Exception\RateLimitException;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The credential namespace: the calling key describing itself.
 */
final class CredentialTest extends TestCase
{
    private const MONTHLY = '{"name": "emails_per_month", "limit": 50000, "used": 9310, "remaining": 40690, '
        . '"period": "2026-10"}';

    private const FULL_PLAN = ', "plan": {"code": "00000000-0000-0000-0000-000000000003", "name": "Momentum", '
        . '"status": "active", "quotas": [{"name": "emails_per_day", "limit": 5000, "used": 120, '
        . '"remaining": 4880, "period": "2026-10-19"}, ' . self::MONTHLY . ']}';

    /**
     * The calling key's description, with the given plan JSON spliced in.
     */
    private static function key(string $plan): string
    {
        return '{"object": "api_key", "key_id": "k3v9x2m8q1w7r4t6y0p5s2a8", "name": "production-backend", '
            . '"description": null, "created_at": "2026-09-02T08:14:03Z", '
            . '"last_auth_at": "2026-10-19T16:41:22Z", "allowed_source_ips": ["203.0.113.0/24"], '
            . '"domain": {"name": "example.com"}, "organization": {"name": "Example Ltd", "alias": "example"}'
            . $plan . '}';
    }

    public function testInfoGetsTheCallingKeyWithNoBodyAndNoIdempotencyKey(): void
    {
        $this->client(200, self::key(self::FULL_PLAN))->credential->info();

        $request = $this->sentRequest();
        self::assertSame('GET', $request->getMethod());
        self::assertSame(self::BASE_URL . 'api-keys/me', (string) $request->getUri());
        self::assertSame('', (string) $request->getBody());
        self::assertFalse($request->hasHeader('Idempotency-Key'));
    }

    public function testInfoDecodesTheKeyItsDomainOrganizationAndPlan(): void
    {
        $key = $this->client(200, self::key(self::FULL_PLAN))->credential->info();

        self::assertSame('api_key', $key->object);
        self::assertSame('k3v9x2m8q1w7r4t6y0p5s2a8', $key->keyId);
        self::assertSame('production-backend', $key->name);
        self::assertNull($key->description);
        self::assertSame('2026-09-02T08:14:03Z', $key->createdAt);
        self::assertSame('2026-10-19T16:41:22Z', $key->lastAuthAt);
        self::assertSame(['203.0.113.0/24'], $key->allowedSourceIps);
        self::assertSame('example.com', $key->domain->name);
        self::assertSame('Example Ltd', $key->organization->name);
        self::assertSame('example', $key->organization->alias);
        self::assertNotNull($key->plan);
        self::assertSame('00000000-0000-0000-0000-000000000003', $key->plan->code);
        self::assertSame('Momentum', $key->plan->name);
        self::assertSame('active', $key->plan->status);
        self::assertCount(2, $key->plan->quotas);
        $daily = $key->plan->quotas[0];
        self::assertSame(
            ['emails_per_day', 5000, 120, 4880, '2026-10-19'],
            [$daily->name, $daily->limit, $daily->used, $daily->remaining, $daily->period],
        );
    }

    #[DataProvider('plansMeaningNone')]
    public function testANullOrAbsentPlanReadsAsNoPlan(string $plan): void
    {
        self::assertNull($this->client(200, self::key($plan))->credential->info()->plan);
    }

    /**
     * @phpstan-return array<string, array{string}>
     */
    public static function plansMeaningNone(): array
    {
        return ['null' => [', "plan": null'], 'absent' => ['']];
    }

    public function testAMonthlyOnlyPlanGetsNoSynthesizedDailyQuota(): void
    {
        $plan = ', "plan": {"code": "c", "name": "Free", "status": "active", "quotas": [' . self::MONTHLY . ']}';
        $key = $this->client(200, self::key($plan))->credential->info();

        self::assertNotNull($key->plan);
        self::assertSame(['emails_per_month'], array_map(static fn ($quota) => $quota->name, $key->plan->quotas));
    }

    public function testARefusedKeyRaisesAnAuthenticationException(): void
    {
        $client = $this->client(403, '{"name":"invalid_api_key","message":"API key is invalid","statusCode":403}');

        try {
            $client->credential->info();
            self::fail('expected an AuthenticationException');
        } catch (AuthenticationException $exception) {
            self::assertSame(ErrorName::InvalidApiKey->value, $exception->errorName);
        }
    }

    public function testARateLimitCarriesRetryAfter(): void
    {
        $client = $this->client(
            429,
            '{"name":"rate_limit_exceeded","message":"slow down","statusCode":429}',
            ['Retry-After' => '59'],
        );

        try {
            $client->credential->info();
            self::fail('expected a RateLimitException');
        } catch (RateLimitException $exception) {
            self::assertSame(59, $exception->retryAfter);
        }
    }

    public function testANonObjectSuccessBodyIsAnSdkErrorNotAnApiError(): void
    {
        try {
            $this->client(200, '"nope"')->credential->info();
            self::fail('expected a MailkubeException');
        } catch (MailkubeException $exception) {
            self::assertNotInstanceOf(ApiException::class, $exception);
            self::assertStringContainsString('Expected a JSON object', $exception->getMessage());
        }
    }
}
