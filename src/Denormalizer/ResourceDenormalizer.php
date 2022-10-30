<?php

declare(strict_types=1);

namespace TwentytwoLabs\Api\Service\Denormalizer;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use TwentytwoLabs\Api\Definition\ResponseDefinition;
use TwentytwoLabs\Api\Service\Pagination\Provider\PaginationProviderInterface;
use TwentytwoLabs\Api\Service\Resource\Collection;
use TwentytwoLabs\Api\Service\Resource\Item;
use TwentytwoLabs\Api\Service\Resource\ResourceInterface;

/**
 * Class ResourceDenormalizer.
 */
class ResourceDenormalizer implements DenormalizerInterface
{
    private ?PaginationProviderInterface $paginationProvider;

    public function __construct($paginationProvider = null)
    {
        $this->paginationProvider = '' === $paginationProvider ? null : $paginationProvider;
    }

    public function denormalize($data, $type, $format = null, array $context = [])
    {
        /** @var ResponseInterface $response */
        $response = $context['response'];

        /** @var RequestInterface $request */
        $request = $context['request'];

        /** @var ResponseDefinition $definition */
        $definition = $context['responseDefinition'];

        if (!$definition->hasBodySchema()) {
            throw new \LogicException(sprintf('Cannot transform the response into a resource. You need to provide a schema for response %d in %s %s', $response->getStatusCode(), $request->getMethod(), $request->getUri()->getPath()));
        }

        $schema = $definition->getBodySchema();
        $meta = ['headers' => $response->getHeaders()];

        if ('array' === $this->getSchemaType($schema)) {
            $pagination = null;
            if (null !== $this->paginationProvider &&
                $this->paginationProvider->supportPagination($data, $response, $definition)
            ) {
                $pagination = $this->paginationProvider->getPagination($data, $response, $definition);
            }

            return new Collection($data, $meta, $pagination);
        }

        return new Item($data, $meta);
    }

    public function supportsDenormalization($data, $type, $format = null): bool
    {
        return ResourceInterface::class === $type;
    }

    private function getSchemaType(\stdClass $schema): string
    {
        if (isset($schema->{'x-type'})) {
            return $schema->{'x-type'};
        }

        if (isset($schema->type)) {
            return $schema->type;
        }

        if (isset($schema->allOf[0]->type)) {
            return $schema->allOf[0]->type;
        }

        throw new \RuntimeException('Cannot extract type from schema');
    }
}
