<?php

declare(strict_types=1);

namespace Obsidiane\AuthBundle\Bridge;

use Obsidiane\AuthBundle\Bridge\Http\BridgeHttpClient;
use Obsidiane\AuthBundle\Bridge\Http\HttpCallOptions;
use Obsidiane\AuthBundle\Bridge\Http\HttpRequestConfig;
use Obsidiane\AuthBundle\Bridge\Utils\QueryBuilder;
use Obsidiane\AuthBundle\Bridge\Utils\UrlResolver;

final class BridgeFacade
{
    public function __construct(
        private readonly BridgeHttpClient $http,
        private readonly BridgeOptions $options,
    ) {
    }

    public function get(string $url, ?HttpCallOptions $opts = null): mixed
    {
        return $this->request(new HttpRequestConfig('GET', $url), $opts);
    }

    /**
     * @param array<string,scalar|array<scalar>|null> $query
     */
    public function getCollection(string $url, array $query = [], ?HttpCallOptions $opts = null): mixed
    {
        return $this->request(new HttpRequestConfig('GET', $url, $query), $opts);
    }

    public function post(string $url, mixed $payload, ?HttpCallOptions $opts = null): mixed
    {
        return $this->request(new HttpRequestConfig('POST', $url, [], $payload), $opts);
    }

    public function patch(string $url, mixed $changes, ?HttpCallOptions $opts = null): mixed
    {
        return $this->request(new HttpRequestConfig('PATCH', $url, [], $changes), $opts);
    }

    public function put(string $url, mixed $payload, ?HttpCallOptions $opts = null): mixed
    {
        return $this->request(new HttpRequestConfig('PUT', $url, [], $payload), $opts);
    }

    public function delete(string $url, ?HttpCallOptions $opts = null): mixed
    {
        return $this->request(new HttpRequestConfig('DELETE', $url), $opts);
    }

    public function request(HttpRequestConfig $req, ?HttpCallOptions $opts = null): mixed
    {
        $resolvedUrl = UrlResolver::resolve($this->options->baseUrl, $req->url);

        $queryString = QueryBuilder::build($req->query);
        $finalUrl = UrlResolver::appendQuery($resolvedUrl, $queryString);

        $headers = $req->headers;
        if ($opts?->headers) {
            $headers = array_merge($headers, $opts->headers);
        }

        $options = [
            'headers' => $headers,
            'timeoutMs' => $opts?->timeoutMs ?? $req->timeoutMs,
            'responseType' => $opts?->responseType ?? $req->responseType,
        ];

        if ($req->body !== null) {
            if (is_array($req->body) || is_object($req->body)) {
                $options['json'] = $req->body;
            } else {
                $options['body'] = $req->body;
            }
        }

        return $this->http->request($req->method, $finalUrl, $options);
    }
}
