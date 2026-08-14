<?php

declare(strict_types=1);

namespace Mailkube\Internal;

/**
 * Reads values out of a decoded response body.
 *
 * Every model's `fromArray` goes through here, which is what keeps "a missing key, an explicit
 * null and a value of the wrong type all mean absent" one decision instead of one per model. It is
 * deliberately forgiving: the contract requires that a server-side field addition or type change
 * never raise inside an already-released client.
 */
final class Payload
{
    /**
     * Read a string field, treating an absent, null or non-scalar value as absent.
     *
     * @phpstan-param array<string, mixed> $body
     */
    public static function text(array $body, string $key): ?string
    {
        $value = $body[$key] ?? null;

        return is_scalar($value) ? (string) $value : null;
    }

    /**
     * Read an integer field, falling back to $default when absent or non-numeric.
     *
     * @phpstan-param array<string, mixed> $body
     */
    public static function integer(array $body, string $key, int $default = 0): int
    {
        $value = $body[$key] ?? null;

        return is_numeric($value) ? (int) $value : $default;
    }

    /**
     * Read a boolean field, falling back to $default when absent or non-boolean.
     *
     * @phpstan-param array<string, mixed> $body
     */
    public static function boolean(array $body, string $key, bool $default = false): bool
    {
        $value = $body[$key] ?? null;

        return is_bool($value) ? $value : $default;
    }

    /**
     * Read a nested object field as an array, treating anything else as empty.
     *
     * @phpstan-param array<string, mixed> $body
     *
     * @phpstan-return array<string, mixed>
     */
    public static function nested(array $body, string $key): array
    {
        $value = $body[$key] ?? null;
        if (!is_array($value)) {
            return [];
        }

        /** @phpstan-var array<string, mixed> $value */
        return $value;
    }

    /**
     * Read a list of strings, dropping anything that is not scalar.
     *
     * @phpstan-param array<string, mixed> $body
     *
     * @phpstan-return list<string>
     */
    public static function strings(array $body, string $key): array
    {
        $value = $body[$key] ?? null;
        if (!is_array($value)) {
            return [];
        }

        $items = [];
        foreach ($value as $item) {
            if (is_scalar($item)) {
                $items[] = (string) $item;
            }
        }

        return $items;
    }

    /**
     * Read a list of objects and build each one through $factory.
     *
     * @phpstan-template T of object
     *
     * @phpstan-param array<string, mixed> $body
     * @phpstan-param callable(array<string, mixed>): T $factory
     *
     * @phpstan-return list<T>
     */
    public static function listOf(array $body, string $key, callable $factory): array
    {
        $value = $body[$key] ?? null;
        if (!is_array($value)) {
            return [];
        }

        $items = [];
        foreach ($value as $item) {
            if (is_array($item)) {
                /** @phpstan-var array<string, mixed> $item */
                $items[] = $factory($item);
            }
        }

        return $items;
    }
}
