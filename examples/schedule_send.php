<?php

/**
 * Schedule one email for later delivery.
 *
 * Run with a real key:
 *
 *     MAILKUBE_API_KEY=mk_... php examples/schedule_send.php
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Mailkube\Client;

// The verified sender this account may send from, and where to send it. Override per
// environment; the fallbacks are placeholders and will be rejected until you set your own.
$sender = getenv('MAILKUBE_FROM') ?: 'Acme <hello@yourdomain.com>';
$to = getenv('MAILKUBE_TO') ?: 'customer@example.com';

$client = new Client();

$email = $client->emails->send(
    from: $sender,
    to: $to,
    subject: 'Your trial ends tomorrow',
    html: '<p>Renew any time.</p>',
    // A DateTimeInterface, or an ISO-8601 string WITH an offset. Must be in the future and
    // inside the plan's scheduling horizon; the server is the authority on both.
    scheduledAt: new DateTimeImmutable('+2 days'),
);

echo $email->id, PHP_EOL;
echo $email->isScheduled() ? "scheduled for {$email->scheduledAt}" : 'sent now', PHP_EOL;
