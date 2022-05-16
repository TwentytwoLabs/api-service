<?php

declare(strict_types=1);

namespace TwentytwoLabs\Api\Service\Denormalizer;

use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use TwentytwoLabs\Api\Service\Resource\Error;
use TwentytwoLabs\Api\Service\Resource\ErrorInterface;

/**
 * Class ErrorDenormalizer.
 */
class ErrorDenormalizer implements DenormalizerInterface
{
    /** {@inheritdoc} */
    public function denormalize($data, $class, $format = null, array $context = [])
    {
        /** @var ResponseInterface $response */
        $response = $context['response'];

        return new Error($response->getStatusCode(), $response->getReasonPhrase(), $data['violations'] ?? []);
    }

    /** {@inheritdoc} */
    public function supportsDenormalization($data, $type, $format = null): bool
    {
        return ErrorInterface::class === $type;
    }
}
