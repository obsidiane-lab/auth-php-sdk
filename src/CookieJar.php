<?php

namespace Obsidiane\AuthBundle;

/**
 * Minimal cookie jar for Symfony HttpClient usage.
 * Stores name=>value pairs and can emit a Cookie header string.
 */
final class CookieJar
{
    /** @var array<string,string> */
    private array $cookies = [];

    /**
     * Ingest Set-Cookie headers from a response.
     * Only stores the cookie name/value (ignores attributes and expiry for simplicity).
     *
     * @param list<string> $setCookies
     */
    public function addFromSetCookie(array $setCookies): void
    {
        foreach ($setCookies as $line) {
            $parts = explode(';', $line);
            if (count($parts) === 0) {
                continue;
            }
            $kv = explode('=', trim($parts[0]), 2);
            if (count($kv) === 2) {
                $this->cookies[$kv[0]] = $kv[1];
            }
        }
    }

    public function get(string $name): ?string
    {
        return $this->cookies[$name] ?? null;
    }

    public function set(string $name, string $value): void
    {
        $this->cookies[$name] = $value;
    }

    public function toHeader(): string
    {
        if (!$this->cookies) {
            return '';
        }
        $pairs = [];
        foreach ($this->cookies as $k => $v) {
            $pairs[] = $k.'='.$v;
        }
        return implode('; ', $pairs);
    }
}
