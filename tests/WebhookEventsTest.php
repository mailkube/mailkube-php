<?php

declare(strict_types=1);

namespace Mailkube\Tests;

use Mailkube\Event\DomainStatusEvent;
use Mailkube\Event\EmailBouncedEvent;
use Mailkube\Event\EmailClickedEvent;
use Mailkube\Event\EmailDeliveredEvent;
use Mailkube\Event\EmailDeliveryDelayedEvent;
use Mailkube\Event\EmailFailedEvent;
use Mailkube\Event\EmailOpenedEvent;
use Mailkube\Event\EmailScheduledEvent;
use Mailkube\Event\EmailSentEvent;
use Mailkube\Event\EmailSuppressedEvent;
use Mailkube\Event\EventCatalogue;
use Mailkube\Event\UnknownEvent;
use Mailkube\Event\WebhookStatusEvent;
use Mailkube\Webhooks;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase as BaseTestCase;

/**
 * The typed webhook catalogue: every arm parses, and every nested block is read from its own key.
 */
final class WebhookEventsTest extends BaseTestCase
{
    private const DELIVERY = ['recipient' => 'b@y.com', 'timestamp' => '2026-08-13T10:00:00Z'];

    private const FAILURE = [
        'recipient' => 'b@y.com',
        'timestamp' => '2026-08-13T10:00:00Z',
        'code' => 550,
        'reason' => 'blocked',
    ];

    private const OPEN = [
        'ipAddress' => '1.2.3.4',
        'userAgent' => 'Mozilla/5.0',
        'timestamp' => '2026-08-13T10:00:00Z',
    ];

    /**
     * The shared `email.*` message context, as the server always sends it.
     *
     * @phpstan-return array<string, mixed>
     */
    private static function messageContext(): array
    {
        return [
            'email_id' => 'abc123',
            'created_at' => '2026-08-13T09:59:00Z',
            'domain' => 'yourdomain.com',
            'subject' => 'Hello world',
            'to' => ['b@y.com'],
            'from' => 'hello@yourdomain.com',
            'tags' => [['name' => 'campaign', 'value' => 'launch']],
        ];
    }

    /**
     * One payload fixture per event type.
     *
     * @phpstan-return array<string, array<string, mixed>>
     */
    private static function payloads(): array
    {
        $message = self::messageContext();

        return [
            'email.sent' => $message + ['sent' => self::DELIVERY],
            'email.delivered' => $message + ['delivery' => self::DELIVERY],
            'email.bounced' => $message + ['bounce' => self::FAILURE],
            'email.delivery_delayed' => $message + ['delay' => self::FAILURE],
            'email.suppressed' => $message + [
                'suppression' => ['recipients' => ['b@y.com'], 'timestamp' => '2026-08-13T10:00:00Z'],
            ],
            'email.scheduled' => $message + [
                'scheduled' => ['scheduled_at' => '2026-08-20T07:00:00Z', 'batch_id' => 'wave'],
            ],
            'email.failed' => $message + [
                'failed' => ['reason' => 'mta_unreachable', 'timestamp' => '2026-08-13T10:00:00Z'],
            ],
            'email.opened' => $message + ['open' => self::OPEN],
            'email.clicked' => $message + ['click' => self::OPEN + ['link' => 'https://x.example/y']],
            'domain.status' => [
                'domain' => 'yourdomain.com',
                'status' => 'active',
                'onboarding_state' => 'complete',
                'previous' => ['status' => 'on_hold', 'onboarding_state' => 'pending'],
            ],
            'webhook.status' => [
                'endpoint_url' => 'https://hooks.example/mailkube',
                'is_active' => false,
                'is_deleted' => false,
                'disabled_reason' => 'repeated_failures',
                'previous' => ['is_active' => true, 'is_deleted' => false, 'disabled_reason' => ''],
            ],
        ];
    }

    /**
     * Render one event body the way the server sends it.
     *
     * @phpstan-param array<string, mixed> $data
     */
    private static function body(string $type, array $data): string
    {
        return (string) json_encode(['type' => $type, 'created_at' => '2026-08-13T10:00:01Z', 'data' => $data]);
    }

    /**
     * Every known type parses into its own class, and never degrades to UnknownEvent.
     *
     * @phpstan-param class-string $expected
     */
    #[DataProvider('knownEvents')]
    public function testEachKnownEventParsesIntoItsOwnClass(string $type, string $expected): void
    {
        $event = Webhooks::parseEvent(self::body($type, self::payloads()[$type]));

        self::assertInstanceOf($expected, $event);
        self::assertNotInstanceOf(UnknownEvent::class, $event);
        self::assertSame($type, $event->type);
        self::assertSame('2026-08-13T10:00:01Z', $event->createdAt);
    }

    /**
     * @phpstan-return array<string, array{string, class-string}>
     */
    public static function knownEvents(): array
    {
        return [
            'sent' => ['email.sent', EmailSentEvent::class],
            'delivered' => ['email.delivered', EmailDeliveredEvent::class],
            'bounced' => ['email.bounced', EmailBouncedEvent::class],
            'delayed' => ['email.delivery_delayed', EmailDeliveryDelayedEvent::class],
            'suppressed' => ['email.suppressed', EmailSuppressedEvent::class],
            'scheduled' => ['email.scheduled', EmailScheduledEvent::class],
            'failed' => ['email.failed', EmailFailedEvent::class],
            'opened' => ['email.opened', EmailOpenedEvent::class],
            'clicked' => ['email.clicked', EmailClickedEvent::class],
            'domain' => ['domain.status', DomainStatusEvent::class],
            'webhook' => ['webhook.status', WebhookStatusEvent::class],
        ];
    }

    public function testTheCatalogueMatchesTheDocumentedEventList(): void
    {
        // Hand-written on purpose: derived from the map it would assert nothing, and a dropped or
        // misspelled row silently routes a wired-up event to UnknownEvent with no other failure.
        self::assertSame([
            'email.sent',
            'email.delivered',
            'email.bounced',
            'email.delivery_delayed',
            'email.suppressed',
            'email.scheduled',
            'email.failed',
            'email.opened',
            'email.clicked',
            'domain.status',
            'webhook.status',
        ], self::catalogueTypes());
    }

    /**
     * Return the catalogue's registered types.
     *
     * Behind a method with a declared return type on purpose: read inline, static analysis folds
     * `array_keys(EventCatalogue::MAP)` to its literal value, decides the comparison above is
     * always true, and refuses to compile the one assertion that catches a dropped arm.
     *
     * @phpstan-return list<string>
     */
    private static function catalogueTypes(): array
    {
        return array_keys(EventCatalogue::MAP);
    }

    public function testEveryCatalogueEntryHasAParsePayload(): void
    {
        // Forces a new event to arrive with a fixture, so the arm above is actually exercised.
        self::assertSame(array_keys(EventCatalogue::MAP), array_keys(self::payloads()));
    }

    public function testEveryKnownEventIsCoveredByTheParseProvider(): void
    {
        $covered = array_column(self::knownEvents(), 0);
        sort($covered);
        $catalogue = array_keys(EventCatalogue::MAP);
        sort($catalogue);

        self::assertSame($catalogue, $covered);
    }

    public function testTheMessageContextIsReadOnEveryEmailEvent(): void
    {
        $event = Webhooks::parseEvent(self::body('email.delivered', self::payloads()['email.delivered']));
        self::assertInstanceOf(EmailDeliveredEvent::class, $event);

        self::assertSame('abc123', $event->data->emailId);
        self::assertSame('yourdomain.com', $event->data->domain);
        self::assertSame('Hello world', $event->data->subject);
        self::assertSame(['b@y.com'], $event->data->to);
        self::assertSame('hello@yourdomain.com', $event->data->from);
        self::assertCount(1, $event->data->tags);
        self::assertSame('campaign', $event->data->tags[0]->name);
    }

    public function testTheDeliveryAndSentBlocksAreReadFromTheirOwnKeys(): void
    {
        $delivered = Webhooks::parseEvent(self::body('email.delivered', self::payloads()['email.delivered']));
        $sent = Webhooks::parseEvent(self::body('email.sent', self::payloads()['email.sent']));

        self::assertInstanceOf(EmailDeliveredEvent::class, $delivered);
        self::assertInstanceOf(EmailSentEvent::class, $sent);
        self::assertSame('b@y.com', $delivered->data->delivery->recipient);
        self::assertSame('b@y.com', $sent->data->sent->recipient);
        self::assertSame('2026-08-13T10:00:00Z', $sent->data->sent->timestamp);
    }

    public function testTheBounceAndDelayBlocksAreReadFromTheirOwnKeys(): void
    {
        $bounced = Webhooks::parseEvent(self::body('email.bounced', self::payloads()['email.bounced']));
        $delayed = Webhooks::parseEvent(self::body('email.delivery_delayed', self::payloads()['email.delivery_delayed']));

        self::assertInstanceOf(EmailBouncedEvent::class, $bounced);
        self::assertInstanceOf(EmailDeliveryDelayedEvent::class, $delayed);
        self::assertSame(550, $bounced->data->bounce->code);
        self::assertSame('blocked', $bounced->data->bounce->reason);
        self::assertSame(550, $delayed->data->delay->code);
        self::assertSame('b@y.com', $delayed->data->delay->recipient);
    }

    public function testTheSuppressionBlockIsReadFromItsOwnKey(): void
    {
        $event = Webhooks::parseEvent(self::body('email.suppressed', self::payloads()['email.suppressed']));

        self::assertInstanceOf(EmailSuppressedEvent::class, $event);
        self::assertSame(['b@y.com'], $event->data->suppression->recipients);
        self::assertSame('2026-08-13T10:00:00Z', $event->data->suppression->timestamp);
    }

    public function testTheScheduledBlockIsReadFromItsOwnKey(): void
    {
        $event = Webhooks::parseEvent(self::body('email.scheduled', self::payloads()['email.scheduled']));

        self::assertInstanceOf(EmailScheduledEvent::class, $event);
        self::assertSame('2026-08-20T07:00:00Z', $event->data->scheduled->scheduledAt);
        self::assertSame('wave', $event->data->scheduled->batchId);
    }

    public function testAnUnbatchedScheduledEventCarriesANullBatchId(): void
    {
        $payload = self::payloads()['email.scheduled'];
        $payload['scheduled'] = ['scheduled_at' => '2026-08-20T07:00:00Z', 'batch_id' => null];
        $event = Webhooks::parseEvent(self::body('email.scheduled', $payload));

        self::assertInstanceOf(EmailScheduledEvent::class, $event);
        self::assertNull($event->data->scheduled->batchId);
    }

    public function testTheFailedBlockIsMessageLevelNotPerRecipient(): void
    {
        $event = Webhooks::parseEvent(self::body('email.failed', self::payloads()['email.failed']));

        // email.failed carries no recipient: the message was dropped before transmission.
        self::assertInstanceOf(EmailFailedEvent::class, $event);
        self::assertSame('mta_unreachable', $event->data->failed->reason);
        self::assertSame('2026-08-13T10:00:00Z', $event->data->failed->timestamp);
    }

    public function testTheEngagementBlocksResolveTheirCamelCaseWireKeys(): void
    {
        $opened = Webhooks::parseEvent(self::body('email.opened', self::payloads()['email.opened']));
        $clicked = Webhooks::parseEvent(self::body('email.clicked', self::payloads()['email.clicked']));

        self::assertInstanceOf(EmailOpenedEvent::class, $opened);
        self::assertInstanceOf(EmailClickedEvent::class, $clicked);
        self::assertSame('1.2.3.4', $opened->data->open->ipAddress);
        self::assertSame('Mozilla/5.0', $opened->data->open->userAgent);
        self::assertSame('https://x.example/y', $clicked->data->click->link);
        self::assertSame('1.2.3.4', $clicked->data->click->ipAddress);
    }

    public function testAnEngagementBlockWithoutIpAddressOrUserAgentStillParses(): void
    {
        // The three connection fields are elected per sending domain and off by default, so the
        // server omits their keys on most events. ipAddress and userAgent read as empty strings
        // because they predate the election and stayed non-nullable; country is nullable, so on
        // that field alone this client can tell "not elected" from "elected but blank".
        $payload = self::payloads()['email.opened'];
        $payload['open'] = ['timestamp' => '2026-08-13T10:00:00Z'];

        $opened = Webhooks::parseEvent(self::body('email.opened', $payload));

        self::assertInstanceOf(EmailOpenedEvent::class, $opened);
        self::assertSame('', $opened->data->open->ipAddress);
        self::assertSame('', $opened->data->open->userAgent);
        self::assertNull($opened->data->open->country);
        self::assertSame('2026-08-13T10:00:00Z', $opened->data->open->timestamp);
    }

    public function testAnElectedCountryIsReadAlongsideTheAddress(): void
    {
        $payload = self::payloads()['email.opened'];
        $payload['open'] = [
            'timestamp' => '2026-08-13T10:00:00Z',
            'ipAddress' => '203.0.113.7',
            'country' => 'FR',
        ];

        $opened = Webhooks::parseEvent(self::body('email.opened', $payload));

        self::assertInstanceOf(EmailOpenedEvent::class, $opened);
        self::assertSame('203.0.113.7', $opened->data->open->ipAddress);
        self::assertSame('FR', $opened->data->open->country);
        // Elected separately, so it stays unset even though the address was recorded.
        self::assertSame('', $opened->data->open->userAgent);
    }

    public function testTheDomainStatusPreviousBlockIsRead(): void
    {
        $event = Webhooks::parseEvent(self::body('domain.status', self::payloads()['domain.status']));

        self::assertInstanceOf(DomainStatusEvent::class, $event);
        self::assertSame('active', $event->data->status);
        self::assertSame('complete', $event->data->onboardingState);
        self::assertSame('on_hold', $event->data->previous->status);
        self::assertSame('pending', $event->data->previous->onboardingState);
    }

    public function testTheWebhookStatusPreviousBlockIsRead(): void
    {
        $event = Webhooks::parseEvent(self::body('webhook.status', self::payloads()['webhook.status']));

        self::assertInstanceOf(WebhookStatusEvent::class, $event);
        self::assertFalse($event->data->isActive);
        self::assertSame('repeated_failures', $event->data->disabledReason);
        self::assertTrue($event->data->previous->isActive);
        self::assertSame('', $event->data->previous->disabledReason);
    }

    public function testAnUnknownTypeParsesInsteadOfRaising(): void
    {
        $event = Webhooks::parseEvent(self::body('email.teleported', ['whatever' => 1]));

        self::assertInstanceOf(UnknownEvent::class, $event);
        self::assertSame('email.teleported', $event->type);
        self::assertSame(['whatever' => 1], $event->data);
    }

    public function testAnUnknownFieldOnAKnownEventIsPreserved(): void
    {
        $payload = self::payloads()['email.delivered'] + ['future_field' => 'kept'];
        $event = Webhooks::parseEvent(self::body('email.delivered', $payload));

        // Inverted relative to response models: a receiver that forwards an event keeps fields
        // this SDK version predates.
        self::assertArrayHasKey('data', $event->raw);
        self::assertIsArray($event->raw['data']);
        self::assertSame('kept', $event->raw['data']['future_field']);
    }

    public function testTheNullableMessageFieldsMayAllBeNull(): void
    {
        $payload = [
            'email_id' => 'abc123',
            'created_at' => '2026-08-13T09:59:00Z',
            'domain' => null,
            'subject' => null,
            'to' => null,
            'from' => null,
            'delivery' => self::DELIVERY,
        ];
        $event = Webhooks::parseEvent(self::body('email.delivered', $payload));

        self::assertInstanceOf(EmailDeliveredEvent::class, $event);
        self::assertNull($event->data->domain);
        self::assertNull($event->data->subject);
        self::assertNull($event->data->to);
        self::assertNull($event->data->from);
        // A server predating message tags sends no `tags` key at all.
        self::assertSame([], $event->data->tags);
    }

    public function testAnUndecodableBodyBecomesAnUnknownEvent(): void
    {
        self::assertInstanceOf(UnknownEvent::class, Webhooks::parseEvent('{not json'));
        self::assertInstanceOf(UnknownEvent::class, Webhooks::parseEvent('"a string"'));
    }

    public function testVerifyChecksTheSignatureThenReturnsTheTypedEvent(): void
    {
        $secret = 'whsec_test';
        $body = self::body('email.bounced', self::payloads()['email.bounced']);
        $timestamp = gmdate('c');
        $headers = [
            'X-Webhook-Id' => 'wh_1',
            'X-Webhook-Ts' => $timestamp,
            'X-Webhook-Sig' => 'sha256=' . hash_hmac('sha256', "wh_1.{$timestamp}." . $body, $secret),
        ];

        $event = Webhooks::verify($body, $headers, $secret);

        self::assertInstanceOf(EmailBouncedEvent::class, $event);
        self::assertSame('blocked', $event->data->bounce->reason);
    }
}
