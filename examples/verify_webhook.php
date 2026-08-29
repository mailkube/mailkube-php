<?php

/**
 * Verify a webhook signature without running a server.
 *
 *     php examples/verify_webhook.php path/to/fixture.json [more.json...]
 *
 * webhook_receiver.php shows verification inside a request handler. This one strips that away: it
 * feeds captured deliveries straight to Webhooks::verify so you can see exactly what is accepted
 * and what is not. Useful for testing your own handler against saved payloads.
 *
 * A fixture is JSON: { secret, headers: {...}, body: "<raw body string>", must_verify: bool }.
 * The body must be the EXACT bytes the server sent — re-serializing parsed JSON will not reproduce
 * the signature, which is the single most common integration bug.
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Mailkube\Exception\SignatureVerificationException;
use Mailkube\Webhooks;

// `$argv` exists only when register_argc_argv is on, which is the CLI default but not a guarantee,
// so read the arguments off `$_SERVER` and narrow them rather than assuming either. The sibling
// examples get away with `$argv[1] ?? ...` because the null-coalesce guards the access; slicing
// does not.
$argument_values = $_SERVER['argv'] ?? [];
$paths = [];
if (is_array($argument_values)) {
    foreach (array_slice($argument_values, 1) as $argument) {
        if (is_string($argument)) {
            $paths[] = $argument;
        }
    }
}

if ($paths === []) {
    fwrite(STDERR, "usage: php examples/verify_webhook.php <fixture.json> [more.json...]\n");
    exit(2);
}

$failures = 0;

foreach ($paths as $path) {
    /** @var array{body: string, headers: array<string, string>, secret: string, name?: string, must_verify?: bool} $fixture */
    $fixture = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

    $verified = false;
    $detail = '';
    try {
        $event = Webhooks::verify($fixture['body'], $fixture['headers'], $fixture['secret']);
        $verified = true;
        $detail = 'event ' . $event->type;
    } catch (SignatureVerificationException $e) {
        $detail = $e->getMessage();
    }

    $expected = ($fixture['must_verify'] ?? false) === true;
    $ok = $verified === $expected;
    $failures += $ok ? 0 : 1;
    printf(
        '%s %s: %s (expected %s) %s%s',
        $ok ? 'ok  ' : 'BAD ',
        $fixture['name'] ?? $path,
        $verified ? 'verified' : 'rejected',
        $expected ? 'verified' : 'rejected',
        $detail,
        PHP_EOL,
    );
}

if ($failures > 0) {
    fwrite(STDERR, "{$failures} fixture(s) did not verify as expected\n");
    exit(1);
}
echo 'all fixtures behaved as expected', PHP_EOL;
