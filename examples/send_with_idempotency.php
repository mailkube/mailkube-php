<?php

/**
 * Retry a send safely with an idempotency key.
 *
 *     MAILKUBE_API_KEY=mk_... php examples/send_with_idempotency.php
 *
 * There are no built-in retries in this SDK, so retrying is your call — and a naive retry after a
 * timeout can send the same message twice, because a request that never returned may still have
 * succeeded. An idempotency key makes the retry safe: the server remembers the first response for
 * that key (24 hours by default) and replays it byte for byte instead of sending again.
 *
 * The key is fingerprinted against the request body. Reusing a key with a DIFFERENT body is an
 * error rather than a silent replay, which is what stops a recycled key from swallowing a real
 * second message.
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Mailkube\Client;
use Mailkube\Exception\ApiException;

// The verified sender this account may send from, and where to send it. Override per
// environment; the fallbacks are placeholders and will be rejected until you set your own.
$sender = getenv('MAILKUBE_FROM') ?: 'Acme <hello@yourdomain.com>';
$to = getenv('MAILKUBE_TO') ?: 'customer@example.com';

$client = new Client();

// In real code this is a stable id for the thing you are sending about — an order id, a job id —
// not a random value, otherwise a retry generates a new key and sends twice.
$idempotencyKey = 'order-' . time();

$send = static fn (string $subject) => $client->emails->send(
    from: $sender,
    to: $to,
    subject: $subject,
    html: '<p>Retrying this send cannot duplicate it.</p>',
    text: 'Retrying this send cannot duplicate it.',
    idempotencyKey: $idempotencyKey,
);

$first = $send('Sent at most once');
echo "first  call: {$first->id}", PHP_EOL;

// Pretend the first response never reached us and we retried.
$replay = $send('Sent at most once');
echo "replayed   : {$replay->id}", PHP_EOL;

if ($first->id !== $replay->id) {
    fwrite(STDERR, "expected the same id back, got {$first->id} then {$replay->id} — that is a second send\n");
    exit(1);
}
echo 'same id returned: the retry was replayed, not resent', PHP_EOL;

// Same key, different body: refused rather than replayed.
try {
    $send('A different message entirely');
    fwrite(STDERR, "expected a reused key with a changed body to be rejected\n");
    exit(1);
} catch (ApiException $e) {
    echo "key reuse with a changed body correctly rejected: {$e->errorName}", PHP_EOL;
}
