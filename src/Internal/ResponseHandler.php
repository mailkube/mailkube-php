<?php

declare(strict_types=1);

namespace Mailkube\Internal;

use JsonException;
use Mailkube\Exception\ApiException;
use Mailkube\Exception\AuthenticationException;
use Mailkube\Exception\BadRequestException;
use Mailkube\Exception\ConflictException;
use Mailkube\Exception\InvalidRequestException;
use Mailkube\Exception\MailkubeException;
use Mailkube\Exception\NotFoundException;
use Mailkube\Exception\RateLimitException;
use Mailkube\Exception\ServerException;
use Psr\Http\Message\ResponseInterface;

/**
 * Turns a raw response into a decoded body, or into the mapped exception.
 *
 * This is the **single place** a non-2xx status becomes an exception, so every verb, present and
 * future, reports failures identically. It is a class of its own rather than a method on the
 * transport so that the status-to-class table is the only thing that knows the exception
 * hierarchy.
 */
final class ResponseHandler
{
    /** HTTP status to exception class. Any other 5xx is a ServerException; the rest ApiException. */
    private const STATUS_EXCEPTIONS = [
        400 => BadRequestException::class,
        403 => AuthenticationException::class,
        404 => NotFoundException::class,
        409 => ConflictException::class,
        422 => InvalidRequestException::class,
        429 => RateLimitException::class,
    ];

    /**
     * Return the decoded body of a successful response, or throw the mapped API exception.
     *
     * A 2xx whose body is empty or undecodable yields an empty array. Callers that need a real
     * object should use {@see self::okObject()} instead.
     *
     * @phpstan-return array<string, mixed>
     *
     * @throws ApiException On any non-2xx response, using the subclass chosen by status.
     */
    public function okBody(ResponseInterface $response): array
    {
        return $this->ok($response) ?? [];
    }

    /**
     * Return the decoded body of a successful response, insisting it is a JSON object.
     *
     * A 2xx carrying `"nope"`, `3` or nothing at all is not an API error: the server said the call
     * succeeded. It is an SDK-level failure, because hydrating a model from it would hand the
     * caller a fabricated object with empty fields rather than an error.
     *
     * @phpstan-return array<string, mixed>
     *
     * @throws ApiException On any non-2xx response, using the subclass chosen by status.
     * @throws MailkubeException On a 2xx body that is not a JSON object.
     */
    public function okObject(ResponseInterface $response): array
    {
        $body = $this->ok($response);
        if ($body === null) {
            throw new MailkubeException(
                "Expected a JSON object from a {$response->getStatusCode()} response."
            );
        }

        return $body;
    }

    /**
     * Return the decoded 2xx body, null when it is not an object, or throw the mapped exception.
     *
     * @phpstan-return array<string, mixed>|null
     *
     * @throws ApiException On any non-2xx response, using the subclass chosen by status.
     */
    private function ok(ResponseInterface $response): ?array
    {
        $status = $response->getStatusCode();
        $body = self::decode((string) $response->getBody());
        if ($status >= 200 && $status < 300) {
            return $body;
        }

        /** @var class-string<ApiException> $class */
        $class = self::STATUS_EXCEPTIONS[$status] ?? ($status >= 500 ? ServerException::class : ApiException::class);
        $retryAfter = $response->getHeaderLine('Retry-After');
        $requestId = $response->getHeaderLine('X-Request-Id');
        // Values read out of array<string, mixed> are mixed; narrow before casting.
        $name = $body['name'] ?? null;
        $detail = $body['message'] ?? null;

        throw new $class(
            errorName: is_scalar($name) ? (string) $name : '',
            message: is_scalar($detail) ? (string) $detail : '',
            statusCode: $status,
            body: $body,
            retryAfter: ctype_digit($retryAfter) ? (int) $retryAfter : null,
            requestId: $requestId === '' ? null : $requestId,
        );
    }

    /**
     * Best-effort JSON decode; returns null for an empty or undecodable body.
     *
     * @phpstan-return array<string, mixed>|null
     */
    private static function decode(string $raw): ?array
    {
        if ($raw === '') {
            return null;
        }

        try {
            /** @var mixed $decoded */
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        /** @var array<string, mixed>|null */
        return is_array($decoded) ? $decoded : null;
    }
}
