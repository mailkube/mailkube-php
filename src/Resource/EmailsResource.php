<?php

declare(strict_types=1);

namespace Mailkube\Resource;

use DateTimeInterface;
use Mailkube\Contract\SendTransport;
use Mailkube\Internal\RequestSpec;
use Mailkube\Internal\Serializer;
use Mailkube\Model\Attachment;
use Mailkube\Model\Email;
use Mailkube\Model\Tag;

/**
 * The ``emails`` resource, reached as ``$client->emails``.
 *
 * This is the worked example every new resource copies. Note what it does *not* do: it holds no
 * configuration, performs no I/O, and never imports an HTTP interface. It depends only on the
 * narrowest transport interface its verbs need.
 *
 * PHP 8 named arguments are this SDK's answer to keyword parameters, so a call reads
 * ``$client->emails->send(from: ..., to: ..., subject: ...)`` and stays fully type-checked.
 */
final class EmailsResource
{
    /**
     * Bind the resource to the transport that performs its requests.
     */
    public function __construct(private readonly SendTransport $transport)
    {
    }

    /**
     * Send an email.
     *
     * Supply ``html`` and/or ``text`` for a raw send, or ``templateId`` for a saved template.
     * ``idempotencyKey`` travels as the ``Idempotency-Key`` header rather than in the body.
     * Passing ``scheduledAt`` schedules the send instead of delivering it now; the result then
     * reports {@see Email::isScheduled()}.
     *
     * @phpstan-param string|list<string> $to
     * @phpstan-param string|list<string>|null $cc
     * @phpstan-param string|list<string>|null $bcc
     * @phpstan-param string|list<string>|null $replyTo
     * @phpstan-param array<string, string>|null $headers
     * @phpstan-param list<Attachment>|null $attachments
     * @phpstan-param list<Tag>|null $tags
     * @phpstan-param array<string, string>|null $variables
     */
    public function send(
        string $from,
        string|array $to,
        string $subject,
        ?string $html = null,
        ?string $text = null,
        string|array|null $cc = null,
        string|array|null $bcc = null,
        string|array|null $replyTo = null,
        ?array $headers = null,
        ?array $attachments = null,
        ?array $tags = null,
        ?string $templateId = null,
        ?string $templateVersion = null,
        ?array $variables = null,
        ?string $topic = null,
        ?string $idempotencyKey = null,
        DateTimeInterface|string|null $scheduledAt = null,
        ?string $batchId = null,
    ): Email {
        $body = array_filter([
            'from' => $from,
            'to' => $to,
            'subject' => $subject,
            'html' => $html,
            'text' => $text,
            'cc' => $cc,
            'bcc' => $bcc,
            'reply_to' => $replyTo,
            'headers' => $headers,
            'attachments' => self::render($attachments),
            'tags' => self::render($tags),
            'template_id' => $templateId,
            'template_version' => $templateVersion,
            'variables' => $variables,
            'topic' => $topic,
            'scheduled_at' => $scheduledAt === null ? null : Serializer::toIso($scheduledAt),
            'batch_id' => $batchId,
        ], static fn (mixed $value): bool => $value !== null);

        $spec = new RequestSpec(
            path: 'emails',
            body: $body,
            headers: $idempotencyKey === null ? [] : ['Idempotency-Key' => $idempotencyKey],
        );

        return $this->transport->sendEmail($spec);
    }

    /**
     * Render a list of wire-renderable values, preserving null so array_filter drops the key.
     *
     * @phpstan-param list<Attachment>|list<Tag>|null $items
     *
     * @phpstan-return list<array<string, string>>|null
     */
    private static function render(?array $items): ?array
    {
        if ($items === null) {
            return null;
        }

        return array_map(static fn (Attachment|Tag $item): array => $item->toArray(), $items);
    }
}
