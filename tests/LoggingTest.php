<?php

declare(strict_types=1);

namespace Mailkube\Tests;

use Mailkube\Internal\ErrorLogLogger;
use Mailkube\Internal\Redactor;
use Mailkube\Logging;
use Mailkube\Tests\Support\RecordingLogger;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Log\LogLevel;
use Psr\Log\NullLogger;

/**
 * Opt-in logging: what is emitted, what is redacted, and what the environment can turn on.
 */
final class LoggingTest extends TestCase
{
    /**
     * Leave the environment as we found it, whatever a test set.
     */
    protected function tearDown(): void
    {
        putenv(Logging::ENV_LEVEL);
        parent::tearDown();
    }

    public function testTheSdkIsSilentUnlessALoggerIsGiven(): void
    {
        $client = $this->client();
        $client->emails->send(from: 'a@b.com', to: 'c@d.com', subject: 'Hi', text: 'Hello');

        // Nothing to assert on a NullLogger beyond the fact that the send worked without one.
        self::assertCount(1, $this->sentRequests());
    }

    public function testARequestAndAResponseRecordAreEmitted(): void
    {
        $logger = new RecordingLogger();
        $this->queue();
        $this->clientOverQueue($logger)->emails->send(
            from: 'a@b.com',
            to: 'c@d.com',
            subject: 'Hi',
            text: 'Hello',
        );

        $messages = array_column($logger->records, 'message');
        self::assertContains('mailkube request', $messages);
        self::assertContains('mailkube response', $messages);
    }

    public function testTheApiKeyNeverReachesALogRecord(): void
    {
        $logger = new RecordingLogger();
        $this->queue();
        $this->clientOverQueue($logger)->emails->send(
            from: 'a@b.com',
            to: 'c@d.com',
            subject: 'Hi',
            text: 'Hello',
            idempotencyKey: 'idem-secret',
        );

        $dump = $logger->dump();
        self::assertStringNotContainsString('mk_test', $dump);
        self::assertStringNotContainsString('idem-secret', $dump);
        self::assertStringContainsString('***', $dump);
    }

    public function testNoRecipientOrSubjectReachesALogRecord(): void
    {
        $logger = new RecordingLogger();
        $this->queue();
        $this->clientOverQueue($logger)->emails->send(
            from: 'sender@example.com',
            to: 'recipient@example.com',
            subject: 'Quarterly results',
            text: 'Hello',
        );

        // Bodies are never logged: they carry addresses, subjects and message content.
        $dump = $logger->dump();
        self::assertStringNotContainsString('recipient@example.com', $dump);
        self::assertStringNotContainsString('Quarterly results', $dump);
    }

    public function testRedactionIsCaseInsensitiveAndKeepsEverythingElse(): void
    {
        $redacted = Redactor::headers([
            'AUTHORIZATION' => 'Bearer mk_live_secret',
            'Idempotency-Key' => 'abc',
            'Content-Type' => 'application/json',
        ]);

        self::assertSame([
            'AUTHORIZATION' => '***',
            'Idempotency-Key' => '***',
            'Content-Type' => 'application/json',
        ], $redacted);
    }

    public function testNothingIsEnabledWithoutTheEnvironmentVariable(): void
    {
        self::assertNull(Logging::fromEnvironment());
    }

    public function testTheEnvironmentVariableSelectsTheBuiltInLogger(): void
    {
        putenv(Logging::ENV_LEVEL . '=warning');

        self::assertInstanceOf(ErrorLogLogger::class, Logging::fromEnvironment());
    }

    public function testAnEmptyEnvironmentVariableEnablesNothing(): void
    {
        putenv(Logging::ENV_LEVEL . '=');

        self::assertNull(Logging::fromEnvironment());
    }

    public function testErrorLogFactoryReturnsAPsr3Logger(): void
    {
        self::assertNotInstanceOf(NullLogger::class, Logging::errorLog());
    }

    /**
     * MAILKUBE_LOG carries a level, not an on/off flag: a record below it must be dropped.
     */
    #[DataProvider('levelFiltering')]
    public function testRecordsBelowTheConfiguredLevelAreDropped(string $minimum, string $level, bool $emitted): void
    {
        $written = self::captureErrorLog(static function () use ($minimum, $level): void {
            (new ErrorLogLogger($minimum))->log($level, 'probe-record');
        });

        self::assertSame($emitted, str_contains($written, 'probe-record'));
    }

    /**
     * @phpstan-return array<string, array{string, string, bool}>
     */
    public static function levelFiltering(): array
    {
        return [
            'debug minimum emits debug' => [LogLevel::DEBUG, LogLevel::DEBUG, true],
            'warning minimum drops debug' => [LogLevel::WARNING, LogLevel::DEBUG, false],
            'warning minimum emits error' => [LogLevel::WARNING, LogLevel::ERROR, true],
            'unknown minimum degrades to debug' => ['nonsense', LogLevel::DEBUG, true],
            'unknown level on a record is dropped' => [LogLevel::DEBUG, 'chatty', false],
        ];
    }

    public function testTheWrittenRecordCarriesTheLevelAndTheContext(): void
    {
        $written = self::captureErrorLog(static function (): void {
            (new ErrorLogLogger())->warning('something happened', ['status' => 429]);
        });

        self::assertStringContainsString('mailkube.warning: something happened', $written);
        self::assertStringContainsString('"status":429', $written);
    }

    /**
     * Run $emit with `error_log()` redirected to a temp file, and return what it wrote.
     *
     * @phpstan-param callable(): void $emit
     */
    private static function captureErrorLog(callable $emit): string
    {
        $file = (string) tempnam(sys_get_temp_dir(), 'mailkube-log-');
        $previous = ini_set('error_log', $file);

        try {
            $emit();
        } finally {
            ini_set('error_log', $previous === false ? '' : $previous);
        }

        $written = (string) file_get_contents($file);
        unlink($file);

        return $written;
    }
}
