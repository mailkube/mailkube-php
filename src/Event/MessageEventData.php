<?php

declare(strict_types=1);

namespace Mailkube\Event;

use Mailkube\Internal\Payload;
use Mailkube\Model\Tag;

/**
 * The fields every `email.*` event's `data` block carries.
 *
 * Abstract, and never instantiated: each concrete event's data class extends it and adds exactly
 * one nested block under its own key. Declaring the shared seven once is what keeps nine data
 * classes from being nine copies of the same constructor.
 *
 * `domain`, `subject`, `to` and `from` are always **sent** as keys but their values may be null:
 * the server resolves them through the sending transaction, which a per-recipient event can
 * briefly outlive. `tags` defaults to an empty list for a server predating message tags.
 */
abstract class MessageEventData
{
    public readonly string $emailId;

    public readonly string $createdAt;

    public readonly ?string $domain;

    public readonly ?string $subject;

    /** @phpstan-var list<string>|null */
    public readonly ?array $to;

    public readonly ?string $from;

    /** @phpstan-var list<Tag> */
    public readonly array $tags;

    /**
     * Read the shared fields out of a decoded `data` block.
     *
     * @phpstan-param array<string, mixed> $data
     */
    public function __construct(array $data)
    {
        $this->emailId = Payload::text($data, 'email_id') ?? '';
        $this->createdAt = Payload::text($data, 'created_at') ?? '';
        $this->domain = Payload::text($data, 'domain');
        $this->subject = Payload::text($data, 'subject');
        $this->to = isset($data['to']) && is_array($data['to']) ? Payload::strings($data, 'to') : null;
        $this->from = Payload::text($data, 'from');
        $this->tags = Payload::listOf($data, 'tags', Tag::fromArray(...));
    }
}
