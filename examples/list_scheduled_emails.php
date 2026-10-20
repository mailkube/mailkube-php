<?php

/**
 * List the sends still waiting to go out: one page for the metadata, then the lazy walk for the
 * rows. Read-only: nothing is scheduled, moved or canceled.
 *
 * Run with a real key:
 *
 *     MAILKUBE_API_KEY=mk_... php examples/list_scheduled_emails.php
 *
 * `list()` returns one page plus its pagination block. `iterAll()` is a generator that follows the
 * links the server issues and fetches the next page only once the loop asks for it, so the
 * `break` below means the remaining pages are never requested at all.
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Mailkube\Client;

const SHOWN = 20;

$client = new Client();

$first = $client->scheduledEmails->list(status: 'scheduled');
printf(
    'page %d: %d of %d scheduled, more: %s%s',
    $first->pagination->currentPage,
    count($first->data),
    $first->pagination->totalCount,
    $first->hasMore() ? 'yes' : 'no',
    PHP_EOL,
);

$count = 0;
foreach ($client->scheduledEmails->iterAll(status: 'scheduled') as $email) {
    printf('%s  %s  %s  %s%s', $email->scheduledAt, $email->id, $email->batchId ?? '-', $email->subject ?? '', PHP_EOL);
    if (++$count === SHOWN) {
        echo 'stopped after ', SHOWN, '; later pages were never fetched', PHP_EOL;
        break;
    }
}
