# mailkube-php

[![CI](https://github.com/mailkube/mailkube-php/actions/workflows/ci.yml/badge.svg)](https://github.com/mailkube/mailkube-php/actions/workflows/ci.yml)
[![Packagist](https://img.shields.io/packagist/v/mailkube/mailkube-php)](https://packagist.org/packages/mailkube/mailkube-php)
[![PHP](https://img.shields.io/packagist/php-v/mailkube/mailkube-php)](composer.json)
[![License: Apache 2.0](https://img.shields.io/badge/license-Apache%202.0-blue.svg)](LICENSE)
[![Code of Conduct](https://img.shields.io/badge/Contributor%20Covenant-2.1-purple.svg)](CODE_OF_CONDUCT.md)

The official PHP SDK for mailkube

## Install

```bash
composer require mailkube/mailkube-php
```

## Usage

```php
use Mailkube\Client;

$client = new Client();  // reads MAILKUBE_API_KEY

$email = $client->emails->send(
    from: 'Acme <hello@yourdomain.com>',
    to: 'customer@example.com',
    subject: 'Hello world',
    html: '<p>It works!</p>',
);

echo $email->id, $email->messageId;
```

### The HTTP client

This package talks PSR-18 and ships **no HTTP implementation of its own**, so your application
picks one. Any PSR-18 client plus PSR-17 factories you already have installed are discovered
automatically:

```bash
composer require mailkube/mailkube-php guzzlehttp/guzzle
```

Pass your own when you need it configured (proxies, retry middleware, a framework's
container-bound client, or a test double):

```php
$client = new Client(httpClient: $myPsr18Client, requestFactory: $f, streamFactory: $f);
```

### Configuration

| Option | Constructor | Environment | Default |
|---|---|---|---|
| API key | `apiKey:` | `MAILKUBE_API_KEY` | required |
| Base URL | `baseUrl:` | `MAILKUBE_BASE_URL` | `https://api.mailkube.com/mta/v1/` |
| Timeout | `timeout:` | | 30 seconds |
| Logger | `logger:` (PSR-3) | `MAILKUBE_LOG` | silent |
| User-Agent suffix | `userAgentSuffix:` | | none |

**About the User-Agent suffix.** If your code wraps this SDK — a CLI, an internal service, a
framework integration — pass a `name/version` token and it is appended to the SDK's own, after a
single space: `mailkube-php/1.1.0 mailkube-laravel/0.1.0`. This SDK's token always leads.
Surrounding whitespace is trimmed, and a value containing CR or LF is **ignored** rather than
cleaned up, because a header value that could split the request is not one this package will send.

**About the timeout.** PSR-18 exposes no timeout API, so the value can only reach a concrete
client. Pass no `httpClient:` and the SDK builds a Guzzle or Symfony client carrying it. Inject
your own client and it keeps whatever timeout you configured on it.

There are **no built-in retries**. A `RateLimitException` carries `retryAfter` and a
`ServerException` is safe to retry with backoff, so the calling application decides. Pass
`idempotencyKey:` to make a retry safe.

### Errors

Every error extends `MailkubeException`. Transport failures raise `ConnectionException`;
server errors raise an `ApiException` subclass chosen by status (`BadRequestException`,
`AuthenticationException`, `NotFoundException`, `ConflictException`,
`InvalidRequestException`, `RateLimitException`, `ServerException`), each carrying
`errorName`, `message`, `statusCode`, `retryAfter` and `requestId`. Quote `requestId` when you
report a failed request to support: it identifies the exact request server-side.

```php
use Mailkube\ErrorName;
use Mailkube\Exception\RateLimitException;

try {
    $client->emails->send(...);
} catch (RateLimitException $e) {
    // $e->errorName === ErrorName::RateLimitExceeded->value
    sleep($e->retryAfter ?? 1);
}
```

### Tags

Attach free-form name/value tags to a send. The server denormalizes them onto the sending log, so
you can filter, export and dashboard by tag, and they ride along on delivery webhooks.

```php
use Mailkube\Model\Tag;

$client->emails->send(
    from: 'Acme <hello@yourdomain.com>',
    to: 'customer@example.com',
    subject: 'Welcome',
    html: '<p>Hi!</p>',
    tags: [new Tag('campaign', 'launch'), new Tag('cohort', 'beta')],
);
```

Validation is server-side: names and values are limited to `[A-Za-z0-9_-]`, a name to 16
characters and a value to 32, at most 20 tags per send, names unique. Tag values are not
encrypted, so keep personal data out of them.

## Scheduling

Pass `scheduledAt:` to have the message delivered later instead of now. The send is acknowledged
`202`, `$email->isScheduled()` is true, and the message is manageable until it is due.

```php
$email = $client->emails->send(
    from: 'Acme <hello@yourdomain.com>',
    to: 'customer@example.com',
    subject: 'Your trial ends tomorrow',
    html: '<p>Renew?</p>',
    scheduledAt: new DateTimeImmutable('2026-08-20T07:00:00Z'),
    batchId: 'trial-reminders',   // optional label, only valid alongside scheduledAt
);
```

`scheduledAt:` accepts a `DateTimeInterface` or an ISO-8601 string **with an offset**.

### Managing scheduled emails

```php
$page = $client->scheduledEmails->list(status: 'scheduled', batchId: 'trial-reminders');
$email = $client->scheduledEmails->get($id);
$client->scheduledEmails->update($id, scheduledAt: '2026-08-21T07:00:00Z');
$client->scheduledEmails->cancel($id);
```

`list()` returns one page: `$page->data`, `$page->pagination` and `$page->hasMore()`. To walk
every page, use `iterAll()`, which follows the links the server issues and fetches lazily, so
abandoning it early costs nothing:

```php
foreach ($client->scheduledEmails->iterAll(status: ['scheduled', 'failed']) as $email) {
    echo $email->id, ' ', $email->scheduledAt, ' ', $email->recipients, PHP_EOL;
}
```

Note `$email->recipients` is a **summary string** (`"customer@example.com +2"`), not a list: the
full recipient set stays server-side with the frozen payload.

### Batches

A `batchId` groups scheduled sends so they move or cancel together.

```php
$client->scheduledEmails->batches->update('trial-reminders', '2026-08-22T07:00:00Z');
$result = $client->scheduledEmails->batches->cancel('trial-reminders');
echo $result->canceledCount;   // an unknown batch is a no-op reporting 0, not an error
```

### Scheduling errors

| `errorName` | Status | Meaning |
|---|---|---|
| `scheduling_not_included` | 403 | The plan does not include scheduled sending |
| `scheduled_email_not_found` | 404 | No such scheduled email, or it is not yours |
| `scheduled_email_not_pending` | 422 | Already sent or canceled, so it can no longer be modified |
| `validation_error` | 422 | A bad due time, an inverted range, or a `batchId` over 64 characters |

The scheduling rules are **server-enforced and not validated client-side**: the due time must be
in the future and within your plan's horizon (about 30 days), the list window covers roughly 31
days around now, and a batch label is at most 64 characters. The horizon and window are
configurable per environment; the batch-label limit is fixed.

## Webhooks

`Webhooks::verify()` checks the signature over the **raw** request body and returns a typed event.
Never decode then re-encode before verifying, or the signature will not match.

```php
use Mailkube\Event\EmailBouncedEvent;
use Mailkube\Event\EmailClickedEvent;
use Mailkube\Webhooks;

$event = Webhooks::verify($request->getBody()->getContents(), $headers, $signingSecret);

if ($event instanceof EmailBouncedEvent) {
    suppress($event->data->bounce->recipient, $event->data->bounce->reason);
}

if ($event instanceof EmailClickedEvent) {
    track($event->data->emailId, $event->data->click->link);
}
```

`Webhooks::verifySignature()` returns the raw bytes if you would rather decode them yourself, and
`Webhooks::parseEvent()` parses without verifying (useful in tests).

`Webhooks::sign()` is the mirror, so your own tests can build a valid request without reimplementing
the HMAC from this page:

```php
$timestamp = gmdate('c');
$signature = Webhooks::sign('wh_1', $timestamp, $body, $signingSecret);
```

See `examples/sign_webhook.php`, which signs a delivery and prints a fixture that
`examples/verify_webhook.php` reads back.

### Event types

| Type | Class | `data` block |
|---|---|---|
| `email.sent` | `EmailSentEvent` | `sent` |
| `email.delivered` | `EmailDeliveredEvent` | `delivery` |
| `email.bounced` | `EmailBouncedEvent` | `bounce` |
| `email.delivery_delayed` | `EmailDeliveryDelayedEvent` | `delay` |
| `email.suppressed` | `EmailSuppressedEvent` | `suppression` |
| `email.scheduled` | `EmailScheduledEvent` | `scheduled` |
| `email.failed` | `EmailFailedEvent` | `failed` |
| `email.opened` | `EmailOpenedEvent` | `open` |
| `email.clicked` | `EmailClickedEvent` | `click` |
| `domain.status` | `DomainStatusEvent` | `previous` |
| `webhook.status` | `WebhookStatusEvent` | `previous` |

`email.sent` means the sending infrastructure accepted the message; `email.delivered` means the
receiving server took it. `email.failed` means it was dropped at dispatch and never transmitted,
which is why it carries no recipient, unlike `email.bounced`.

On the `open` and `click` blocks, `ipAddress`, `country` and `userAgent` are recorded only where the
sending domain has elected them, and both settings are off by default. The server omits the key
rather than sending an empty value. `$country` is `null` when it was not recorded, while
`$ipAddress` and `$userAgent` stay non-nullable and read as `''`, so on those two an unelected value
is indistinguishable from a blank one. `$country` can be `null` even where the address was recorded,
because it is resolved at the edge and is not available on every path.

An event type this release does not know becomes an `UnknownEvent` rather than an error, and every
event keeps the whole decoded payload in `$event->raw`, so a receiver that logs or forwards events
never loses a field. That is deliberate: the server gains event types independently of the SDK.

## Logging

Silent by default. Pass any PSR-3 logger, or set `MAILKUBE_LOG` to a level:

```php
use Mailkube\Logging;

$client = new Client(logger: $myPsr3Logger);
$client = new Client(logger: Logging::errorLog('debug'));   // no logging framework to hand
```

```bash
MAILKUBE_LOG=debug php send.php
```

`MAILKUBE_LOG` holds a **level**, not a flag: `MAILKUBE_LOG=warning` suppresses the SDK's
request/response records, which are emitted at debug. The SDK logs the method, URL, redacted
headers, response status and request id. It never logs a request or response body, and the
`Authorization` and `Idempotency-Key` headers are always masked.

## Extending this SDK

Before adding a verb, a resource, a paginated listing or a webhook event, read
[`.rules/SDK_CONTRACT.md`](.rules/SDK_CONTRACT.md) (the decisions every mailkube SDK shares)
and [`.rules/SDK_DESIGN.md`](.rules/SDK_DESIGN.md) (how they are realized in PHP). Both carry
a step-by-step checklist. `emails.send` is the worked example for a single verb, `scheduledEmails`
for a CRUD resource with pagination and a sub-namespace, and `src/Event/` for the webhook catalogue.

Runnable scripts live in [`examples/`](examples/); every checklist ends with adding one.

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md) for the development setup and the quality gates every change
must pass. Security issues: see [SECURITY.md](SECURITY.md).

## License

[Apache-2.0](LICENSE) © 2026 Mailtactic, Corp.
