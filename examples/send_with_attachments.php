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

$client = new Client();

$email = $client->emails->send(
    from: 'Acme <billing@yourdomain.com>',
    to: 'customer@example.com',
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
