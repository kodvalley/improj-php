<?php

declare(strict_types=1);

namespace Kodvalley\Improj\Internal;

use UnexpectedValueException;

/**
 * Typed reads from decoded JSON. A missing or mistyped field throws, which the
 * client reports as an INVALID_RESPONSE error instead of failing silently.
 *
 * @internal
 */
final class Data
{
    /** @codeCoverageIgnore Static helpers only. */
    private function __construct() {}

    /**
     * @param array<mixed> $data
     */
    public static function int(array $data, string $key): int
    {
        $v = $data[$key] ?? null;
        if (!\is_int($v)) {
            throw new UnexpectedValueException(sprintf('Field "%s" must be an integer.', $key));
        }

        return $v;
    }

    /**
     * @param array<mixed> $data
     */
    public static function intOrNull(array $data, string $key): ?int
    {
        $v = $data[$key] ?? null;
        if ($v !== null && !\is_int($v)) {
            throw new UnexpectedValueException(sprintf('Field "%s" must be an integer or null.', $key));
        }

        return $v;
    }

    /**
     * @param array<mixed> $data
     */
    public static function string(array $data, string $key): string
    {
        $v = $data[$key] ?? null;
        if (!\is_string($v)) {
            throw new UnexpectedValueException(sprintf('Field "%s" must be a string.', $key));
        }

        return $v;
    }

    /**
     * @param array<mixed> $data
     */
    public static function stringOrNull(array $data, string $key): ?string
    {
        $v = $data[$key] ?? null;
        if ($v !== null && !\is_string($v)) {
            throw new UnexpectedValueException(sprintf('Field "%s" must be a string or null.', $key));
        }

        return $v;
    }

    /**
     * @param array<mixed> $data
     */
    public static function bool(array $data, string $key): bool
    {
        $v = $data[$key] ?? null;
        if (!\is_bool($v)) {
            throw new UnexpectedValueException(sprintf('Field "%s" must be a boolean.', $key));
        }

        return $v;
    }

    /**
     * @param array<mixed> $data
     *
     * @return array<string, mixed>
     */
    public static function object(array $data, string $key): array
    {
        /** @var mixed $v */
        $v = $data[$key] ?? null;
        if (!\is_array($v)) {
            throw new UnexpectedValueException(sprintf('Field "%s" must be an object.', $key));
        }
        if ($v !== [] && array_is_list($v)) {
            throw new UnexpectedValueException(sprintf('Field "%s" must be an object.', $key));
        }

        return self::assoc($v);
    }

    /**
     * @param array<mixed> $data
     *
     * @return array<string, mixed>|null
     */
    public static function objectOrNull(array $data, string $key): ?array
    {
        return ($data[$key] ?? null) === null ? null : self::object($data, $key);
    }

    /**
     * @param array<mixed> $data
     *
     * @return list<mixed>
     */
    public static function list(array $data, string $key): array
    {
        $v = $data[$key] ?? null;
        if (!\is_array($v) || !array_is_list($v)) {
            throw new UnexpectedValueException(sprintf('Field "%s" must be a list.', $key));
        }

        return $v;
    }

    /**
     * @param array<mixed> $data
     *
     * @return list<string>
     */
    public static function strings(array $data, string $key): array
    {
        $out = [];
        foreach (self::list($data, $key) as $item) {
            if (!\is_string($item)) {
                throw new UnexpectedValueException(sprintf('Field "%s" must be a list of strings.', $key));
            }
            $out[] = $item;
        }

        return $out;
    }

    /**
     * @param array<mixed> $data
     *
     * @return list<int>|null
     */
    public static function intsOrNull(array $data, string $key): ?array
    {
        if (($data[$key] ?? null) === null) {
            return null;
        }
        $out = [];
        foreach (self::list($data, $key) as $item) {
            if (!\is_int($item)) {
                throw new UnexpectedValueException(sprintf('Field "%s" must be a list of integers.', $key));
            }
            $out[] = $item;
        }

        return $out;
    }

    /**
     * A list of objects.
     *
     * @param array<mixed> $data
     *
     * @return list<array<string, mixed>>
     */
    public static function objects(array $data, string $key): array
    {
        $out = [];
        foreach (self::list($data, $key) as $item) {
            if (!\is_array($item)) {
                throw new UnexpectedValueException(sprintf('Field "%s" must be a list of objects.', $key));
            }
            $out[] = self::assoc($item);
        }

        return $out;
    }

    /**
     * @param array<mixed> $value
     *
     * @return array<string, mixed>
     */
    public static function assoc(array $value): array
    {
        $out = [];
        /** @var mixed $v */
        foreach ($value as $k => $v) {
            /** @psalm-suppress MixedAssignment the values are untyped JSON by design */
            $out[(string) $k] = $v;
        }

        return $out;
    }
}
