<?php

/**
 * Send a saved template instead of raw content.
 *
 *     MAILKUBE_API_KEY=mk_... php examples/send_with_template.php <template-uuid>
 *
 * The template must exist on the sending domain and be published — a draft or deleted one is a
 * `template_not_found`.
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Mailkube\Client;

// The verified sender this account may send from, and where to send it. Override per
// environment; the fallbacks are placeholders and will be rejected until you set your own.
$sender = getenv('MAILKUBE_FROM') ?: 'Acme <hello@yourdomain.com>';
$to = getenv('MAILKUBE_TO') ?: 'customer@example.com';

$templateId = $argv[1] ?? getenv('MAILKUBE_TEMPLATE_ID');
if (!$templateId) {
    fwrite(STDERR, "usage: php examples/send_with_template.php <template-uuid>\n");
    exit(2);
}

$client = new Client();

$email = $client->emails->send(
    from: $sender,
    to: $to,
    subject: 'Welcome aboard',
    // A send carries EITHER raw content (html/text) or a template, never both.
    templateId: $templateId,
    templateVersion: 'latest',
    variables: ['first_name' => 'Ada', 'plan' => 'Pro'],
);

echo $email->id, PHP_EOL;
