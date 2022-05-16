<?php

declare(strict_types=1);

namespace TwentytwoLabs\Api\Service;

use Http\Client\HttpClient;
use Http\Discovery\HttpClientDiscovery;
use Http\Discovery\MessageFactoryDiscovery;
use Http\Discovery\UriFactoryDiscovery;
use Http\Message\MessageFactory;
use Http\Message\UriFactory;
use JsonSchema\Validator;
use Psr\Cache\CacheItemPoolInterface;
use Rize\UriTemplate;
use Symfony\Component\Serializer\Encoder\ChainDecoder;
use Symfony\Component\Serializer\Encoder\EncoderInterface;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\SerializerInterface;
use TwentytwoLabs\Api\Decoder\Adapter\SymfonyDecoderAdapter;
use TwentytwoLabs\Api\Factory\CachedSchemaFactoryDecorator;
use TwentytwoLabs\Api\Factory\SwaggerSchemaFactory;
use TwentytwoLabs\Api\Schema;
use TwentytwoLabs\Api\Service\Denormalizer\ResourceDenormalizer;
use TwentytwoLabs\Api\Service\Pagination\Provider\PaginationProviderInterface;
use TwentytwoLabs\Api\Validator\MessageValidator;

/**
 * Class ApiServiceBuilder.
 */
class ApiServiceBuilder
{
    private ?HttpClient $httpClient = null;
    private ?MessageFactory $messageFactory = null;
    private ?UriFactory $uriFactory = null;
    private ?SerializerInterface $serializer = null;
    private array $denormalizers = [];
    private array $encoders = [];
    private Schema $schema;
    private ?CacheItemPoolInterface $cache = null;
    private array $config = [];
    private ?PaginationProviderInterface $paginationProvider = null;
    private MessageValidator $requestValidator;

    public static function create(): ApiServiceBuilder
    {
        return new static();
    }

    public function withCacheProvider(CacheItemPoolInterface $cache): self
    {
        $this->cache = $cache;

        return $this;
    }

    public function withHttpClient(HttpClient $httpClient): self
    {
        $this->httpClient = $httpClient;

        return $this;
    }

    public function withMessageFactory(MessageFactory $messageFactory): self
    {
        $this->messageFactory = $messageFactory;

        return $this;
    }

    public function withUriFactory(UriFactory $uriFactory): self
    {
        $this->uriFactory = $uriFactory;

        return $this;
    }

    public function withSerializer(SerializerInterface $serializer): self
    {
        $this->serializer = $serializer;

        return $this;
    }

    public function withEncoder(EncoderInterface $encoder): self
    {
        $this->encoders[] = $encoder;

        return $this;
    }

    public function withDenormalizer(NormalizerInterface $normalizer): self
    {
        $this->denormalizers[] = $normalizer;

        return $this;
    }

    public function withPaginationProvider(PaginationProviderInterface $paginationProvider): self
    {
        $this->paginationProvider = $paginationProvider;

        return $this;
    }

    public function withBaseUri(string $baseUri): self
    {
        $this->config['baseUri'] = $baseUri;

        return $this;
    }

    public function disableRequestValidation(): self
    {
        $this->config['validateRequest'] = false;

        return $this;
    }

    public function enableResponseValidation(): self
    {
        $this->config['validateResponse'] = true;

        return $this;
    }

    public function returnResponse(): self
    {
        $this->config['returnResponse'] = true;

        return $this;
    }

    public function build(string $schemaPath): ApiService
    {
        // Build serializer
        if (null === $this->serializer) {
            if (empty($this->encoders)) {
                $this->encoders = [new JsonEncoder(), new XmlEncoder()];
            }

            if (empty($this->denormalizers)) {
                $this->denormalizers[] = new ResourceDenormalizer($this->paginationProvider);
            }

            $this->serializer = new Serializer($this->denormalizers, $this->encoders);
        }

        if (null === $this->uriFactory) {
            $this->uriFactory = UriFactoryDiscovery::find();
        }

        if (null === $this->messageFactory) {
            $this->messageFactory = MessageFactoryDiscovery::find();
        }

        if (null === $this->httpClient) {
            $this->httpClient = HttpClientDiscovery::find();
        }

        $schemaFactory = new SwaggerSchemaFactory();
        if (null !== $this->cache) {
            $schemaFactory = new CachedSchemaFactoryDecorator($this->cache, $schemaFactory);
        }

        $this->schema = $schemaFactory->createSchema($schemaPath);

        if (!isset($this->requestValidator)) {
            $this->requestValidator = new MessageValidator(
                new Validator(),
                new SymfonyDecoderAdapter(new ChainDecoder($this->encoders))
            );
        }

        return new ApiService(
            $this->uriFactory,
            new UriTemplate(),
            $this->httpClient,
            $this->messageFactory,
            $this->schema,
            $this->requestValidator,
            $this->serializer,
            $this->config
        );
    }
}
