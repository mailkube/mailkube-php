<?php

declare(strict_types=1);

namespace Mailkube\Contract;

use Mailkube\Internal\RequestSpec;
use Mailkube\Model\Email;

/**
 * A transport capable of sending an email.
 *
 * This is the dependency-inversion boundary: resources depend on this narrow interface rather
 * than on a concrete client or on any HTTP library, so they can be driven by a test double and
 * stay ignorant of PSR-18 entirely.
 *
 * There is deliberately **one interface per capability** rather than one wide one. A resource
 * that only sends must not acquire a dependency on every other verb. A new capability adds an
 * interface; it never widens an existing one.
 */
interface SendTransport
{
    /**
     * Perform the request described by $spec and build the accepted-send result.
     */
    public function sendEmail(RequestSpec $spec): Email;
}
