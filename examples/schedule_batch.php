<?php

/**
 * Schedule several emails under one batch label, then move or cancel the whole batch.
 *
 *     MAILKUBE_API_KEY=mk_... php examples/schedule_batch.php
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Mailkube\Client;

// The verified sender this account may send from, and where to send it. Override per
// environment; the fallbacks are placeholders and will be rejected until you set your own.
$sender = getenv('MAILKUBE_FROM') ?: 'Acme <hello@yourdomain.com>';
$to = getenv('MAILKUBE_TO') ?: 'customer@example.com';

$client = new Client();
$batch = 'trial-reminders';
$due = new DateTimeImmutable('+2 days');

foreach (['a@example.com', 'b@example.com', 'c@example.com'] as $recipient) {
    // batchId is only valid alongside scheduledAt.
    $client->emails->send(
        from: $sender,
        to: $recipient,
        subject: 'Your trial ends soon',
        html: '<p>Renew any time.</p>',
        scheduledAt: $due,
        batchId: $batch,
    );
}

$moved = $client->scheduledEmails->batches->update($batch, new DateTimeImmutable('+3 days'));
echo "rescheduled {$moved->rescheduledCount} to {$moved->scheduledAt}", PHP_EOL;

$canceled = $client->scheduledEmails->batches->cancel($batch);
echo "canceled {$canceled->canceledCount}", PHP_EOL;
