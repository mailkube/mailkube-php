<?php

/**
 * Send a saved template instead of raw content.
 *
 *     MAILKUBE_API_KEY=mk_... php examples/send_with_template.php
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Mailkube\Client;

$client = new Client();

$email = $client->emails->send(
    from: 'Acme <hello@yourdomain.com>',
    to: 'customer@example.com',
    subject: 'Welcome aboard',
    // A send carries EITHER raw content (html/text) or a template, never both.
    templateId: '00000000-0000-4000-8000-000000000000',
    templateVersion: 'latest',
    variables: ['first_name' => 'Ada', 'plan' => 'Pro'],
);

echo $email->id, PHP_EOL;
