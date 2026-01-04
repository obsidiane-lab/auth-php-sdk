<?php

declare(strict_types=1);

namespace Obsidiane\AuthBundle\Bridge;

use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;

final readonly class FacadeFactory
{
    public function __construct(
        private BridgeFacade $bridge,
        private SerializerInterface $serializer,
    ) {
    }

    /**
     * @param class-string<T> $modelClass
     * @param array<string,mixed> $serializerContext
     *
     * @return ResourceFacade<T>
     *
     * @template T of object
     */
    public function create(string $url, string $modelClass, array $serializerContext = []): ResourceFacade
    {
        if (!$this->serializer instanceof NormalizerInterface) {
            throw new \RuntimeException('Serializer must implement NormalizerInterface');
        }
        if (!$this->serializer instanceof DenormalizerInterface) {
            throw new \RuntimeException('Serializer must implement DenormalizerInterface');
        }

        return new ResourceFacade(
            $this->bridge,
            $this->serializer,
            $this->serializer,
            $url,
            $modelClass,
            $serializerContext
        );
    }
}
