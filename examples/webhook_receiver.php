<?php

/**
 * A framework-agnostic webhook receiver.
 *
 * Serve it with PHP's built-in server and point an endpoint at it:
 *
 *     MAILKUBE_WEBHOOK_SECRET=whsec_... php -S 127.0.0.1:8080 examples/webhook_receiver.php
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Mailkube\Event\DomainStatusEvent;
use Mailkube\Event\EmailBouncedEvent;
use Mailkube\Event\EmailClickedEvent;
use Mailkube\Event\EmailDeliveredEvent;
use Mailkube\Event\UnknownEvent;
use Mailkube\Event\WebhookStatusEvent;
use Mailkube\Exception\SignatureVerificationException;
use Mailkube\Webhooks;

// Verify against the RAW received bytes. Decoding and re-encoding changes them and the
// signature will not match.
$raw = (string) file_get_contents('php://input');
/** @var array<string, string> $headers */
$headers = function_exists('getallheaders') ? getallheaders() : [];
$secret = (string) getenv('MAILKUBE_WEBHOOK_SECRET');

try {
    $event = Webhooks::verify($raw, $headers, $secret);
} catch (SignatureVerificationException $exception) {
    http_response_code(400);
    echo $exception->getMessage();

    return;
}

// X-Webhook-Id is stable across retries: deduplicate on it before acting.
$deliveryId = $headers['X-Webhook-Id'] ?? $headers['x-webhook-id'] ?? '';

$summary = match (true) {
    $event instanceof EmailDeliveredEvent => "delivered to {$event->data->delivery->recipient}",
    $event instanceof EmailBouncedEvent => sprintf(
        'bounced %s: %d %s',
        $event->data->bounce->recipient,
        $event->data->bounce->code,
        $event->data->bounce->reason,
    ),
    $event instanceof EmailClickedEvent => "clicked {$event->data->click->link}",
    $event instanceof DomainStatusEvent => "domain {$event->data->domain} is {$event->data->status}",
    // Worth handling: this is how you learn your own endpoint was auto-disabled.
    $event instanceof WebhookStatusEvent => "endpoint disabled: {$event->data->disabledReason}",
    // A type this SDK version does not know is a valid parse, never an error.
    $event instanceof UnknownEvent => "unhandled type {$event->type}",
    default => "received {$event->type}",
};

error_log("[{$deliveryId}] {$summary}");

// Acknowledge fast; do the real work out of band.
http_response_code(204);
