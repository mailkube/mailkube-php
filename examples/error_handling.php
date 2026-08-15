<?php

/**
 * The errors you will actually hit, and how to tell them apart.
 *
 *     MAILKUBE_API_KEY=mk_... php examples/error_handling.php
 *
 * Every API failure arrives as an ApiException subclass carrying `errorName` — the server's stable
 * machine-readable name — alongside `statusCode`, `requestId` and `retryAfter`. Branch on
 * `errorName` (compare against the ErrorName enum), never on the human-readable message, which is
 * free to change.
 *
 * Nothing here sends a message: each call is designed to be refused.
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Mailkube\Client;
use Mailkube\ErrorName;
use Mailkube\Exception\ApiException;

// The verified sender this account may send from, and where to send it. Override per
// environment; the fallbacks are placeholders and will be rejected until you set your own.
$sender = ($e = getenv('MAILKUBE_FROM')) === false || $e === '' ? 'Acme <hello@yourdomain.com>' : $e;
$to = ($e = getenv('MAILKUBE_TO')) === false || $e === '' ? 'customer@example.com' : $e;

$client = new Client();
$failures = 0;

$expect = static function (string $label, ErrorName $expected, callable $run) use (&$failures): void {
    try {
        $run();
    } catch (ApiException $e) {
        $ok = $e->errorName === $expected->value;
        $failures += $ok ? 0 : 1;
        printf('%s %s: %s (%d)%s', $ok ? 'ok  ' : 'BAD ', $label, $e->errorName, $e->statusCode, PHP_EOL);

        return;
    }
    printf('BAD  %s: expected %s, but the call succeeded%s', $label, $expected->value, PHP_EOL);
    ++$failures;
};

// A message with no body at all: html, text and templateId are mutually required-one-of.
$expect('missing body', ErrorName::ValidationError, static fn () => $client->emails->send(
    from: $sender,
    to: $to,
    subject: 'No body',
));

// scheduledAt must carry an offset and be strictly in the future.
$expect('past scheduledAt', ErrorName::ValidationError, static fn () => $client->emails->send(
    from: $sender,
    to: $to,
    subject: 'Yesterday',
    text: '...',
    scheduledAt: new DateTimeImmutable('-1 minute'),
));

// batchId is a grouping label for scheduled sends and means nothing without scheduledAt.
$expect('batchId without scheduledAt', ErrorName::ValidationError, static fn () => $client->emails->send(
    from: $sender,
    to: $to,
    subject: 'Ungrouped',
    text: '...',
    batchId: 'b1',
));

// A sent email has left the scheduled collection, so filtering for it is a contract error rather
// than an empty page — the distinction tells you your assumption was wrong.
$expect('list status "sent"', ErrorName::ValidationError, static fn () => $client->scheduledEmails->list(status: 'sent'));

// A bad key is refused identically whether it is malformed, unknown or absent, so nothing about
// the key space leaks.
$expect('bad api key', ErrorName::InvalidApiKey, static function () use ($sender, $to): void {
    $anonymous = new Client(apiKey: 'mk_notarealkey_' . str_repeat('0', 64));
    $anonymous->emails->send(from: $sender, to: $to, subject: 'Nope', text: '...');
});

if ($failures > 0) {
    fwrite(STDERR, "{$failures} case(s) did not behave as documented\n");
    exit(1);
}
echo 'all error cases behaved as documented', PHP_EOL;
