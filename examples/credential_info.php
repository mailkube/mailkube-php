<?php

/**
 * Describe the API key this client authenticates with: its domain, organization, plan and
 * send-quota usage. Nothing is sent, and the key itself is never printed.
 *
 * Run with a real key:
 *
 *     MAILKUBE_API_KEY=mk_... php examples/credential_info.php
 *
 * The key is checked exactly as a send checks it, so this is also how to verify a key before
 * using it: a key that cannot send raises AuthenticationException (`invalid_api_key`). The call
 * counts as a use of the key, and plan figures can be up to 30 seconds old.
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Mailkube\Client;
use Mailkube\Exception\AuthenticationException;

$client = new Client();

try {
    $key = $client->credential->info();
} catch (AuthenticationException $e) {
    fwrite(STDERR, 'this key cannot send: ' . $e->errorName . PHP_EOL);
    exit(1);
}

printf('key %s (%s) sends for %s%s', $key->keyId, $key->name, $key->domain->name, PHP_EOL);
printf('organization %s (%s)%s', $key->organization->name, $key->organization->alias, PHP_EOL);
echo 'allowed from ', $key->allowedSourceIps === [] ? 'any address' : implode(', ', $key->allowedSourceIps), PHP_EOL;

// A plan without a daily limit lists the monthly quota only; an absent quota is no limit.
if ($key->plan === null) {
    echo 'no subscription', PHP_EOL;
    exit(0);
}

printf('plan %s (%s)%s', $key->plan->name, $key->plan->status, PHP_EOL);
foreach ($key->plan->quotas as $quota) {
    printf('  %s: %d of %d recipients in %s%s', $quota->name, $quota->used, $quota->limit, $quota->period, PHP_EOL);
}
