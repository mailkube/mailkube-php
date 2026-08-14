# SDK Design: the PHP realization of the cross-SDK contract

Load this alongside [`SDK_CONTRACT.md`](SDK_CONTRACT.md) when adding a **resource, verb,
response model, paginated listing, or webhook event**.

`SDK_CONTRACT.md` is the shared, language-neutral constitution: configuration, layering,
naming, response-model rules, pagination, the error model, and the webhook contract, all of
which every mailkube SDK implements identically. It is shared verbatim across every mailkube SDK
and maintained centrally, so it is not edited here.

**This file covers only what is specific to PHP.** A deliberate deviation from the contract
belongs here, never in the shared file: that is what keeps a PHP-shaped decision from silently
becoming an obligation on Go or Ruby.

## The layers, in files

| Layer | Files | May know about |
|---|---|---|
| **Client / IO** | `Internal/HttpTransport.php` | PSR-18 / PSR-7 |
| **Core** | `Internal/Config.php`, `Internal/RequestBuilder.php`, `Internal/ResponseHandler.php`, `Internal/RequestSpec.php`, `Internal/Serializer.php` | PSR-17 factories only |
| **Resources** | `Resource/*.php` | a transport interface plus its own request shaping |
| **Types** | `Model/*.php`, `ErrorName.php`, `Exception/*.php` | nothing |

`Client.php` is the composition root: it resolves config, wires the collaborators and exposes
the resources. It performs no I/O itself.

Only `HttpTransport` imports an HTTP interface. A resource or model that does is a bug.

## HTTP is PSR-18, and it is injectable

`Client` accepts an optional `ClientInterface` plus PSR-17 request/stream factories. Leave
them null and `php-http/discovery` finds whatever implementation is installed; pass your own
to use a configured Guzzle client, a framework's container-bound client, or a test double.

This is the PHP translation of the pilot's injectable escape hatch, and it is what the
"depend on interfaces at boundaries (DIP)" rule in `AGENTS.md` demands. **Do not hardcode a
concrete HTTP client.** The package therefore ships no HTTP implementation of its own; the
consuming application chooses one, exactly as the PSR-18 ecosystem intends.

**The contract's concurrency obligation is satisfied by construction here, and there is deliberately
no test for it.** PHP's standard runtime has no threads, and fibers are cooperative and
single-threaded, so no two callers can be inside a verb at once. `Client` is also immutable after
construction. A test spawning nothing and asserting nothing would read like coverage of a guarantee
nobody actually checked, which the contract explicitly rules out. If a consumer runs this SDK under
a parallel runtime (Swoole, parallel), the PSR-18 client they inject is the thing that must be safe,
and that is theirs to choose.

**There is one client, and it is synchronous**, which is the contract's sync-only case. PSR-18
defines a synchronous interface; the async counterpart is `php-http`'s `HttpAsyncClient`, which is
not a PSR and drags in a promise library. Taking it would put a runtime dependency and a second
public surface into every consumer to serve a minority of callers, so this SDK does not. Callers
needing concurrency use their own runtime (Fibers, an async framework's client) around the
injected PSR-18 implementation.

## PHP idioms that realize the contract

- **Named arguments replace keyword parameters.** `EmailsResource::send()` takes one named
  argument per API field, so a call stays fully type-checked without an untyped options
  array. This is the PHP answer to the pilot's `Unpack[TypedDict]`.
- **The request body is built with `array_filter`**, not a chain of `if ($x !== null)`.
  That keeps cyclomatic complexity far below the phpmd limit of 10 as fields are added, and
  it is why an unset field is absent from the wire rather than sent as null.
- **Models are `final` with promoted `readonly` properties** and a `fromArray` factory that
  ignores unknown keys, so a server-side field addition can never break a released client.
  Reading a field is `Internal/Payload.php`'s job, not each model's, so "absent, null and
  wrong-typed all mean absent" is one decision rather than one per model.
- **Request builders are private static methods on the resource**, not standalone functions.
  The contract asks for standalone builders so several client flavours share one definition of
  each URL and body; PHP ships a single synchronous flavour, so there is no second caller to
  share with and a module-level function would be un-PHP-ish for no gain.
- **There is one transport interface per capability** (`Contract/SendTransport.php`), with one
  deliberate exception: `Contract/TypedTransport.php` is capability-agnostic. Every verb that maps
  a 2xx body onto a model wants exactly `request(spec, factory)`, and giving each its own
  single-method interface would multiply interfaces per verb without separating anything a caller
  can observe. It takes a **factory callable** (`ScheduledEmail::fromArray(...)`) rather than a
  class name so the hydration is checked at the call site, and so "every model has a static
  `fromArray`" stays a convention instead of a published interface that v1.0.0 would freeze.
- **There is deliberately no abstract `Resource` base class.** PHP has a single client
  flavour, so a base class would deduplicate nothing and would only add inheritance depth
  (which phpmd gates). Extract one when a second resource genuinely shares behaviour.
- **`iterAll()` yields in a `foreach`, never with `yield from`.** `yield from` preserves the
  source array's keys, so each page would re-yield `0..n-1` and `iterator_to_array()` would
  silently return only the last page. `tests/PaginationTest.php` materializes the iterator for
  exactly this reason; a `foreach`-only test passes while the bug ships.
- **Webhook events are a sealed-by-convention class hierarchy**, where python has a discriminated
  union of models. `abstract WebhookEvent` carries `type`/`createdAt`/`raw`; each of the eleven
  types is a `final` subclass declaring its own typed `data`. `instanceof` narrowing is what gives
  a PHP caller the guarantee python gets from the union, and `Event/EventCatalogue.php`'s `MAP` is
  both the parse table and the known-type set so an event cannot be half-registered.
- **Event data and context classes may extend an `abstract` base.** `MessageEventData` holds the
  seven fields every `email.*` event shares and `InteractionContext` the three an open and a click
  share. Leaves stay `final`; the bases are never instantiated. Without them, nine data classes
  are nine copies of one constructor, which the duplication gate rejects outright.
- **The version comes from `Composer\InstalledVersions`**, never a literal, so the
  User-Agent reports the released version by construction. The leading `v` is stripped: releases
  are tagged `vX.Y.Z` and Composer records the tag's pretty form verbatim, so without the strip
  the User-Agent would read `mailkube-php/v1.0.0` and break the contract's `<lang>/<version>` shape.

## Timeout, logging, and what PSR cannot express

- **The timeout only reaches a client this SDK builds.** PSR-18 has no timeout API, so
  `Internal/Discovery.php` constructs Guzzle or Symfony with the configured value when the caller
  injected nothing. A client the caller injects is already configured and is used untouched; that
  is documented in the README rather than silently ignored. The alternative was dropping the
  constructor argument, which the contract's own configuration table asks every SDK to keep.
- **PHP's realization of the contract's "opt-in enable function" is PSR-3 injection.** There is no
  library-global logger registry equivalent to python's `logging.getLogger("mailkube")`, so
  `Mailkube\Logging` offers a factory (`Logging::errorLog()`) whose result the caller passes to
  `new Client(logger: ...)`, plus `Logging::fromEnvironment()` for `MAILKUBE_LOG`.
- **`MAILKUBE_LOG` holds a level, not a flag**, matching python, and an unrecognised value degrades
  to `debug` rather than throwing: the value arrives from the environment, and a typo there must not
  take the calling application down.
- **This SDK actually logs**, at debug, where python currently ships the plumbing with zero call
  sites. `Internal/TransportLog.php` is the only caller. Bodies are never logged at any level, and
  headers pass through `Internal/Redactor.php` first.

## Two tool frictions, and how they are settled

1. **`@param` versus `@phpstan-param`.** phpcs's Squiz sniff enforces a legacy type
   vocabulary (`integer`, not `int`) and strict positional ordering on `@param` tags, which
   contradicts both native types and phpstan. The settlement: native type hints are the
   source of truth, generic shapes are declared with **`@phpstan-param` / `@phpstan-return`**,
   and plain `@param` is not used. `phpcs.xml` already excludes `MissingParamTag` for this
   reason.
2. **Import order is alphabetical** (php-cs-fixer `ordered_imports`), so the package's own
   namespace interleaves with third-party ones. Files here are ordered for the default
   `Mailkube` root namespace. If you regenerate with a different `composer_vendor`, run
   `composer format` once.

## Where the shared rules are enforced

| Contract rule | Enforced in |
|---|---|
| Key/base-URL resolution, default headers | `Config::__construct()`, `Config::defaultHeaders()` |
| Origin guard and URL joining | `Config::buildUrl()` |
| One place maps non-2xx to an exception | `ResponseHandler::ok()` |
| A 2xx body that is not an object is an SDK error | `ResponseHandler::okObject()` |
| Status-to-class table | `ResponseHandler::STATUS_EXCEPTIONS` |
| Idempotency key lifted to a header | `EmailsResource::send()` |
| ISO-8601 rendering, JSON encoding, query values | `Internal/Serializer.php` |
| Version from package metadata, without the tag prefix | `Config::version()`, asserted in `tests/ConfigTest.php` |
| HTTP client injection | `Client::__construct($httpClient)` |
| Discovery failure becomes a MailkubeException | `Internal/Discovery.php` |
| Path segments URL-escaped | `ScheduledEmailsResource::item()`, `ScheduledEmailBatchesResource::item()` |
| Pagination follows the server's `next` link, same-origin only | `ScheduledEmailsResource::iterAll()` + `Config::buildUrl()` |
| Documented error names available as constants | `ErrorName`, asserted in `tests/ErrorNameTest.php` |
| Webhook signature verification | `Webhooks.php` (no client instance needed) |
| The event union is the catalogue | `Event/EventCatalogue::MAP`, asserted in `tests/WebhookEventsTest.php` |
| Logging silent by default, redacted, level-aware | `Logging`, `Internal/TransportLog.php`, `Internal/Redactor.php` |

## Tests

The DI seam is the test seam: `tests/TestCase.php` builds a client over
`php-http/mock-client` with `nyholm/psr7` factories, so the suite makes zero network calls
and still exercises the real request building, error mapping and response parsing.

Coverage is **line only** here. PHPUnit/pcov report covered-versus-total statements but no
reliable branch metric, so there is nothing further to gate on.

## Two tests that must keep failing for the right reason

Both guard a defect that is invisible to the obvious test, so if you rewrite either one, break the
production code once and watch it fail before trusting it again.

- **`PaginationTest::testMaterializingTheIteratorKeepsEveryPage()`** materializes the generator
  rather than iterating it. Swap the `foreach`/`yield` in `iterAll()` for `yield from` and it fails
  with 2 items instead of 5; every other pagination test still passes.
- **`WebhookEventsTest::testTheCatalogueMatchesTheDocumentedEventList()`** compares
  `EventCatalogue::MAP`'s keys against a hand-written literal. Delete one row and the event
  degrades to `UnknownEvent` at runtime, which nothing else notices. The comparison goes through
  `catalogueTypes()` because phpstan folds the inline call to a literal and rejects the assertion
  as always-true.

## Adding to this SDK

Every layer now has a worked example to copy: `emails.send` for a single verb,
`scheduledEmails` for a CRUD resource with a sub-namespace and pagination, and `Event/` for the
webhook catalogue. Follow the matching checklist in `SDK_CONTRACT.md`, and remember that a new
`.rules/*.md` file needs a row in `AGENTS.md` in the same change.
