<?php

/**
 * Send against a mailing-list topic.
 *
 *     MAILKUBE_API_KEY=mk_... php examples/send_with_topic.php [topic-slug]
 *
 * A topic is a subscription group your recipients can opt out of individually, and `topic` is the
 * slug you configured for it (16 characters max). Sending under one means the unsubscribe link
 * removes the recipient from that topic rather than from everything you send.
 *
 * The slug must already exist and be enabled on the sending domain's apex. An unknown or disabled
 * slug is rejected outright, BEFORE the message is charged or queued — so a typo costs you
 * nothing, but it does not silently fall back to sending untopiced either. The second half of
 * this example triggers that rejection on purpose.
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Mailkube\Client;
use Mailkube\ErrorName;
use Mailkube\Exception\ApiException;

// The verified sender this account may send from, and where to send it. Override per
// environment; the fallbacks are placeholders and will be rejected until you set your own.
$sender = getenv('MAILKUBE_FROM') ?: 'Acme <hello@yourdomain.com>';
$to = getenv('MAILKUBE_TO') ?: 'customer@example.com';
$topic = $argv[1] ?? 'newsletter';

$client = new Client();

$email = $client->emails->send(
    from: $sender,
    to: $to,
    subject: sprintf('Sent under the "%s" topic', $topic),
    html: '<p>Unsubscribing from this removes you from this topic only.</p>',
    text: 'Unsubscribing from this removes you from this topic only.',
    topic: $topic,
);
echo "accepted {$email->id} under topic {$topic}", PHP_EOL;

// The negative case: a slug that was never configured.
try {
    $client->emails->send(
        from: $sender,
        to: $to,
        subject: 'This one never leaves the building',
        text: 'You should not be reading this.',
        topic: 'no-such-topic',
    );
    fwrite(STDERR, "expected an unknown topic to be rejected, but it was accepted\n");
    exit(1);
} catch (ApiException $e) {
    if ($e->errorName !== ErrorName::TopicNotFound->value) {
        throw $e;
    }
    echo "unknown topic correctly rejected: {$e->errorName}", PHP_EOL;
}
