<?php

/**
 * Send with a file attached.
 *
 *     MAILKUBE_API_KEY=mk_... php examples/send_with_attachments.php
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Mailkube\Client;
use Mailkube\Model\Attachment;

// The verified sender this account may send from, and where to send it. Override per
// environment; the fallbacks are placeholders and will be rejected until you set your own.
$sender = getenv('MAILKUBE_FROM') ?: 'Acme <hello@yourdomain.com>';
$to = getenv('MAILKUBE_TO') ?: 'customer@example.com';

$client = new Client();

$email = $client->emails->send(
    from: $sender,
    to: $to,
    subject: 'Your invoice',
    html: '<p>Attached.</p>',
    attachments: [
        // Raw bytes; the SDK base64-encodes them for the wire.
        Attachment::fromBytes('invoice.pdf', (string) file_get_contents(__FILE__), 'application/pdf'),
        // Or content you encoded yourself.
        Attachment::fromBase64('note.txt', base64_encode('thanks!'), 'text/plain'),
    ],
);

echo $email->id, PHP_EOL;
