<?php

/**
 * List, inspect, reschedule and cancel scheduled emails.
 *
 *     MAILKUBE_API_KEY=mk_... php examples/manage_scheduled_emails.php
 *
 * The example schedules its own email under a unique batch id and then works only inside that
 * batch. That is deliberate: an unfiltered list()/iterAll() walks every pending send on the
 * account, and taking $page->data[0] from it means rescheduling and cancelling whichever message
 * happened to come back first — someone else's. Scoping to a batch you just created keeps the
 * example bounded, repeatable, and safe to run against a live key.
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Mailkube\Client;

// The verified sender this account may send from, and where to send it. Override per
// environment; the fallbacks are placeholders and will be rejected until you set your own.
$sender = getenv('MAILKUBE_FROM') ?: 'Acme <hello@yourdomain.com>';
$to = getenv('MAILKUBE_TO') ?: 'customer@example.com';

$client = new Client();
$batchId = 'example-manage-' . time();

$created = $client->emails->send(
    from: $sender,
    to: $to,
    subject: 'Scheduled for management',
    html: '<p>This one exists to be listed, moved and cancelled.</p>',
    scheduledAt: new DateTimeImmutable('+1 hour'),
    batchId: $batchId,
);
echo "scheduled {$created->id} in batch {$batchId}", PHP_EOL;

// Reads are rate-limited (60/minute by default), so pace a script that walks pages rather than
// relying on catching the 429.
usleep(600_000);

// One page, with the pagination block alongside it.
$page = $client->scheduledEmails->list(status: 'scheduled', batchId: $batchId);
echo "page {$page->pagination->currentPage} of {$page->pagination->totalCount} total", PHP_EOL;
usleep(600_000);

// Or walk every page: iterAll follows the links the server issues and fetches lazily, so
// breaking out early costs nothing.
foreach ($client->scheduledEmails->iterAll(status: ['scheduled', 'failed'], batchId: $batchId) as $email) {
    // `recipients` is a summary string ("a@b.com +2"), not a list.
    echo "{$email->id} {$email->scheduledAt} {$email->recipients}", PHP_EOL;
}

$first = $page->data[0] ?? null;
if ($first === null) {
    echo 'nothing scheduled in this batch', PHP_EOL;

    return;
}

usleep(600_000);
$reloaded = $client->scheduledEmails->get($first->id);
echo "subject: {$reloaded->subject}", PHP_EOL;
usleep(600_000);

$client->scheduledEmails->update($first->id, scheduledAt: new DateTimeImmutable('+5 days'));
usleep(600_000);
$canceled = $client->scheduledEmails->cancel($first->id);
echo "{$canceled->id} is now {$canceled->status}", PHP_EOL;
