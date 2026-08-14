<?php

/**
 * Send one email.
 *
 * Run with a real key:
 *
 *     MAILKUBE_API_KEY=mk_... php examples/send_email.php
 *
 * Every checklist in .rules/SDK_CONTRACT.md ends with "a README section and a runnable script
 * in examples/". This directory is that home; add one script per verb.
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Mailkube\Client;

$client = new Client();

$email = $client->emails->send(
    from: 'Acme <hello@yourdomain.com>',
    to: 'customer@example.com',
    subject: 'Hello world',
    html: '<p>It works!</p>',
);

echo $email->id, ' ', $email->messageId ?? '(no message id)', PHP_EOL;
