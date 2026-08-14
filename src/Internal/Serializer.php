<?php

declare(strict_types=1);

namespace Mailkube\Internal;

use DateTimeInterface;
use JsonException;
use Mailkube\Exception\MailkubeException;

/**
 * Rendering PHP values for the wire.
 *
 * One home for every "how does this value become JSON or a query string" decision. Nothing here
 * validates: the server is the authority on what a value means, and its error names are richer
 * than anything the SDK would reproduce. These functions only make values transmissible.
 */
final class Serializer
{
    /**
     * Render an instant as ISO-8601, passing an already-formatted string through unchanged.
     */
    public static function toIso(DateTimeInterface|string $value): string
    {
        return $value instanceof DateTimeInterface ? $value->format(DateTimeInterface::ATOM) : $value;
    }

    /**
     * Render one query-string parameter.
     *
     * A list becomes a comma-separated value rather than a repeated parameter: the API accepts
     * both, and a flat map of strings keeps the transport seam simple in every SDK that mirrors
     * this design. Booleans render as `True`/`False` to match the other SDKs exactly; no filter
     * takes one today, and a lowercase spelling here would be a silent divergence rather than a
     * fix.
     *
     * @phpstan-param DateTimeInterface|string|bool|int|list<DateTimeInterface|string|bool|int> $value
     */
    public static function queryValue(DateTimeInterface|string|bool|int|array $value): string
    {
        if (is_array($value)) {
            return implode(',', array_map(static fn (mixed $item): string => self::scalar($item), $value));
        }

        return self::scalar($value);
    }

    /**
     * Render one non-list query value.
     */
    private static function scalar(mixed $value): string
    {
        if ($value instanceof DateTimeInterface) {
            return self::toIso($value);
        }
        if (is_bool($value)) {
            return $value ? 'True' : 'False';
        }

        return is_scalar($value) ? (string) $value : '';
    }

    /**
     * Encode a request body as JSON.
     *
     * @phpstan-param array<string, mixed> $body
     *
     * @throws MailkubeException If the body contains a value JSON cannot represent.
     */
    public static function toJson(array $body): string
    {
        try {
            return json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } catch (JsonException $exception) {
            throw new MailkubeException('Could not encode the request body as JSON.', 0, $exception);
        }
    }
}
