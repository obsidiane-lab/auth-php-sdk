<?php

declare(strict_types=1);

namespace Obsidiane\AuthBundle\Bridge\Utils;

final class QueryBuilder
{
    /**
     * @param array<string,scalar|array<scalar>|null> $query
     */
    public static function build(array $query = []): string
    {
        if ($query === []) {
            return '';
        }

        $parts = [];
        $consumed = [];

        if (array_key_exists('page', $query)) {
            self::addParam($parts, 'page', $query['page']);
            $consumed['page'] = true;
        }

        if (array_key_exists('itemsPerPage', $query)) {
            self::addParam($parts, 'itemsPerPage', $query['itemsPerPage']);
            $consumed['itemsPerPage'] = true;
        }

        if (isset($query['filters']) && is_array($query['filters'])) {
            $consumed['filters'] = true;
            foreach ($query['filters'] as $key => $value) {
                self::addParam($parts, (string) $key, $value);
            }
        }

        foreach ($query as $key => $value) {
            if (isset($consumed[$key])) {
                continue;
            }
            self::addParam($parts, (string) $key, $value);
        }

        return implode('&', $parts);
    }

    /**
     * @param array<string> $parts
     */
    private static function addParam(array &$parts, string $key, mixed $value): void
    {
        if ($value === null) {
            return;
        }

        if (is_array($value)) {
            foreach ($value as $entry) {
                self::addParam($parts, $key, $entry);
            }
            return;
        }

        $encodedKey = rawurlencode($key);
        $encodedValue = rawurlencode(self::stringifyScalar($value));
        $parts[] = $encodedKey.'='.$encodedValue;
    }

    private static function stringifyScalar(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return (string) $value;
    }
}
