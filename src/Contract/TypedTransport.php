<?php

declare(strict_types=1);

namespace Mailkube\Contract;

use Mailkube\Internal\RequestSpec;

/**
 * A transport that performs a request and hydrates the response into a model.
 *
 * The second dependency-inversion boundary, alongside {@see SendTransport}. Resources depend on the
 * narrowest interface their verbs need, so they can be driven by a test double and stay ignorant of
 * PSR-18 entirely.
 *
 * Where `SendTransport` is capability-bound (it sends an email and returns an Email), this one is
 * capability-agnostic on purpose: every verb that maps a 2xx body onto a model wants exactly this,
 * and giving each its own single-method interface would multiply interfaces per verb without
 * separating anything a caller can observe. That is a deliberate reading of the contract's
 * "one interface per capability" rule, recorded in `.rules/SDK_DESIGN.md`.
 */
interface TypedTransport
{
    /**
     * Perform the request described by $spec and build the result from the decoded 2xx body.
     *
     * The model arrives as a factory rather than a class name: `ScheduledEmail::fromArray(...)` is
     * checked at the call site, where a class-string would only be checked at runtime, and it keeps
     * "every model has a static fromArray" a convention rather than a published interface that
     * v1.0.0 would freeze.
     *
     * @phpstan-template T of object
     *
     * @phpstan-param callable(array<string, mixed>): T $model
     *
     * @phpstan-return T
     */
    public function request(RequestSpec $spec, callable $model): object;
}
