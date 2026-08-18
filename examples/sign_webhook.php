<?php

/**
 * Build a signed webhook delivery, the way the platform builds one.
 *
 *     MAILKUBE_WEBHOOK_SECRET=whsec_... php examples/sign_webhook.php > fixture.json
 *     php examples/verify_webhook.php fixture.json
 *
 * Production code verifies webhooks; it does not sign them. This is for the other side of your
 * test suite: a fake endpoint, a replay tool, a fixture generator. Webhooks::sign computes the
 * HMAC exactly as Webhooks::verify checks it, so a fixture built here cannot drift from the
 * verifier the way a hand-rolled hash_hmac eventually does.
 *
 * The signature covers the RAW body bytes, so sign the exact string you will send — never a
 * re-encoding of parsed JSON.
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Mailkube\Webhooks;

$configured = getenv('MAILKUBE_WEBHOOK_SECRET');
$secret = ($configured === false || $configured === '') ? 'whsec_example' : $configured;

$deliveryId = 'wh_' . bin2hex(random_bytes(8));
$timestamp = gmdate('c');
$body = json_encode([
    'type' => 'email.delivered',
    'data' => ['id' => 'msg_abc123', 'delivery' => ['recipient' => 'customer@example.com']],
], JSON_THROW_ON_ERROR);

$headers = [
    'X-Webhook-Id' => $deliveryId,
    'X-Webhook-Ts' => $timestamp,
    'X-Webhook-Sig' => Webhooks::sign($deliveryId, $timestamp, $body, $secret),
];

// Round-trip it here so a broken fixture fails loudly rather than in the suite that consumes it.
$event = Webhooks::verify($body, $headers, $secret);
fwrite(STDERR, "signed and verified: {$event->type}" . PHP_EOL);

echo json_encode([
    'name' => 'a freshly signed delivery',
    'secret' => $secret,
    'headers' => $headers,
    'body' => $body,
    'must_verify' => true,
], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), PHP_EOL;
