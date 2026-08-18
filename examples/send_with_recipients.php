<?php

/**
 * Every recipient field and custom headers on one message.
 *
 *     MAILKUBE_API_KEY=mk_... php examples/send_with_recipients.php
 *
 * `to`, `cc`, `bcc` and `replyTo` each take a single address or an array. The account limit is 50
 * recipients per message, counted across to + cc + bcc.
 *
 * Custom headers carry your own metadata. The API caps them at 20 per message, header names match
 * [A-Za-z0-9-] up to 64 characters, and no value may contain CR or LF.
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Mailkube\Client;

// The verified sender this account may send from, and where to send it. Override per
// environment; the fallbacks are placeholders and will be rejected until you set your own.
$sender = ($e = getenv('MAILKUBE_FROM')) === false || $e === '' ? 'Acme <hello@yourdomain.com>' : $e;
$to = ($e = getenv('MAILKUBE_TO')) === false || $e === '' ? 'customer@example.com' : $e;

$client = new Client();

$email = $client->emails->send(
    from: $sender,
    to: [$to],
    cc: $to,
    bcc: [$to],
    // Replies go somewhere other than the sending address.
    replyTo: 'support@yourdomain.com',
    subject: 'Every recipient field at once',
    html: '<p>to, cc, bcc and reply-to on a single message.</p>',
    text: 'to, cc, bcc and reply-to on a single message.',
    headers: [
        'X-Campaign-Id' => 'recipients-demo',
        'X-Customer-Tier' => 'gold',
    ],
);

echo $email->id, PHP_EOL;
