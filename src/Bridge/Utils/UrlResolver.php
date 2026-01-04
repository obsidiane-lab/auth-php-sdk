<?php

declare(strict_types=1);

namespace Obsidiane\AuthBundle\Bridge\Utils;

final class UrlResolver
{
    public static function resolve(string $baseUrl, string $path): string
    {
        if (preg_match('#^https?://#i', $path) === 1) {
            return $path;
        }

        if (str_starts_with($path, '//')) {
            return $path;
        }

        $normalizedBase = rtrim($baseUrl, '/');
        $normalizedPath = str_starts_with($path, '/') ? $path : '/'.$path;

        return $normalizedBase.$normalizedPath;
    }

    public static function appendQuery(string $url, string $queryString): string
    {
        if ($queryString === '') {
            return $url;
        }

        $separator = str_contains($url, '?') ? '&' : '?';

        return $url.$separator.$queryString;
    }
}
