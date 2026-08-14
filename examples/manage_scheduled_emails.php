<?php

/**
 * List, inspect, reschedule and cancel scheduled emails.
 *
 *     MAILKUBE_API_KEY=mk_... php examples/manage_scheduled_emails.php
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Mailkube\Client;

$client = new Client();

// One page, with the pagination block alongside it.
$page = $client->scheduledEmails->list(status: 'scheduled');
echo "page {$page->pagination->currentPage} of {$page->pagination->totalCount} total", PHP_EOL;

// Or walk every page: iterAll follows the links the server issues and fetches lazily, so
// breaking out early costs nothing.
foreach ($client->scheduledEmails->iterAll(status: ['scheduled', 'failed']) as $email) {
    // `recipients` is a summary string ("a@b.com +2"), not a list.
    echo "{$email->id} {$email->scheduledAt} {$email->recipients}", PHP_EOL;
}

$first = $page->data[0] ?? null;
if ($first === null) {
    echo 'nothing scheduled', PHP_EOL;

    return;
}

$reloaded = $client->scheduledEmails->get($first->id);
echo "subject: {$reloaded->subject}", PHP_EOL;

$client->scheduledEmails->update($first->id, scheduledAt: new DateTimeImmutable('+5 days'));
$canceled = $client->scheduledEmails->cancel($first->id);
echo "{$canceled->id} is now {$canceled->status}", PHP_EOL;
