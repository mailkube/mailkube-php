<?php

/**
 * Send with message tags, which ride along on the sending log and on delivery webhooks.
 *
 *     MAILKUBE_API_KEY=mk_... php examples/send_with_tags.php
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Mailkube\Client;
use Mailkube\Model\Tag;

$client = new Client();

$email = $client->emails->send(
    from: 'Acme <hello@yourdomain.com>',
    to: 'customer@example.com',
    subject: 'Welcome aboard',
    html: '<p>Glad you are here.</p>',
    // Names and values are limited server-side to [A-Za-z0-9_-], 16 and 32 characters, 20 tags
    // per send, names unique. Tag values are not encrypted: keep personal data out of them.
    tags: [new Tag('campaign', 'launch'), new Tag('cohort', 'beta')],
);

echo $email->id, PHP_EOL;
