<?php

declare(strict_types=1);

namespace Obsidiane\AuthBundle\Bridge;

use Obsidiane\AuthBundle\Bridge\Http\HttpCallOptions;
use Obsidiane\AuthBundle\Bridge\Http\HttpRequestConfig;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * @template T of object
 */
final class ResourceFacade
{
    /**
     * @var array<string,mixed>
     */
    private array $serializerContext;

    /**
     * @param class-string<T> $modelClass
     * @param array<string,mixed> $serializerContext
     */
    public function __construct(
        private readonly BridgeFacade $bridge,
        private readonly NormalizerInterface $normalizer,
        private readonly DenormalizerInterface $denormalizer,
        private readonly string $resourceUrl,
        private readonly string $modelClass,
        array $serializerContext = [],
    ) {
        $this->serializerContext = array_merge(
            [AbstractNormalizer::ALLOW_EXTRA_ATTRIBUTES => true],
            $serializerContext
        );
    }

    /**
     * @param array<string,scalar|array<scalar>|null> $query
     *
     * @return Collection<T>
     */
    public function getCollection(array $query = [], ?HttpCallOptions $opts = null): Collection
    {
        $payload = $this->bridge->request(new HttpRequestConfig('GET', $this->resourceUrl, $query), $opts);

        return $this->hydrateCollection($payload);
    }

    /**
     * @return T
     */
    public function get(string $iri, ?HttpCallOptions $opts = null): object
    {
        $payload = $this->bridge->request(new HttpRequestConfig('GET', $iri), $opts);

        return $this->hydrateItem($payload);
    }

    /**
     * @param object|array<string,mixed> $payload
     *
     * @return T
     */
    public function post(object|array $payload, ?HttpCallOptions $opts = null): object
    {
        $body = $this->normalizePayload($payload);
        $response = $this->bridge->request(new HttpRequestConfig('POST', $this->resourceUrl, [], $body), $opts);

        return $this->hydrateItem($response);
    }

    /**
     * @param object|array<string,mixed> $changes
     *
     * @return T
     */
    public function patch(string $iri, object|array $changes, ?HttpCallOptions $opts = null): object
    {
        $body = $this->normalizePayload($changes);
        $response = $this->bridge->request(new HttpRequestConfig('PATCH', $iri, [], $body), $opts);

        return $this->hydrateItem($response);
    }

    /**
     * @param object|array<string,mixed> $payload
     *
     * @return T
     */
    public function put(string $iri, object|array $payload, ?HttpCallOptions $opts = null): object
    {
        $body = $this->normalizePayload($payload);
        $response = $this->bridge->request(new HttpRequestConfig('PUT', $iri, [], $body), $opts);

        return $this->hydrateItem($response);
    }

    public function delete(string $iri, ?HttpCallOptions $opts = null): void
    {
        $this->bridge->request(new HttpRequestConfig('DELETE', $iri), $opts);
    }

    public function request(HttpRequestConfig $req, ?HttpCallOptions $opts = null): mixed
    {
        return $this->bridge->request($req, $opts);
    }

    /**
     * @return T
     */
    private function hydrateItem(mixed $payload): object
    {
        if (!is_array($payload)) {
            throw new \RuntimeException('ResourceFacade: expected array payload for item hydration.');
        }

        return $this->denormalizer->denormalize($payload, $this->modelClass, 'json', $this->serializerContext);
    }

    /**
     * @return Collection<T>
     */
    private function hydrateCollection(mixed $payload): Collection
    {
        if (!is_array($payload)) {
            throw new \RuntimeException('ResourceFacade: expected array payload for collection hydration.');
        }

        $rows = [];
        if (isset($payload['member']) && is_array($payload['member'])) {
            $rows = $payload['member'];
        } elseif (isset($payload['items']) && is_array($payload['items'])) {
            $rows = $payload['items'];
        }

        $items = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $items[] = $this->denormalizer->denormalize($row, $this->modelClass, 'json', $this->serializerContext);
        }

        $context = isset($payload['@context']) && is_array($payload['@context']) ? $payload['@context'] : null;
        $view = isset($payload['view']) && is_array($payload['view']) ? $payload['view'] : null;
        $search = isset($payload['search']) && is_array($payload['search']) ? $payload['search'] : null;

        return new Collection(
            $items,
            isset($payload['totalItems']) ? (int) $payload['totalItems'] : null,
            isset($payload['@id']) ? (string) $payload['@id'] : null,
            $payload['@type'] ?? null,
            $context,
            $view,
            $search
        );
    }

    /**
     * @param object|array<string,mixed> $payload
     *
     * @return array<string,mixed>
     */
    private function normalizePayload(object|array $payload): array
    {
        if (is_array($payload)) {
            return $payload;
        }

        $normalized = $this->normalizer->normalize($payload, 'json', $this->serializerContext);
        if (!is_array($normalized)) {
            throw new \RuntimeException('ResourceFacade: serializer returned non-array payload.');
        }

        return $normalized;
    }
}
