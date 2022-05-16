<?php

declare(strict_types=1);

namespace TwentytwoLabs\Api\Service\Tests;

use Http\Client\HttpClient;
use Http\Message\MessageFactory;
use Http\Message\UriFactory;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Http\Message\UriInterface;
use Symfony\Component\Serializer\Encoder\EncoderInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;
use TwentytwoLabs\Api\Schema;
use TwentytwoLabs\Api\Service\ApiService;
use TwentytwoLabs\Api\Service\ApiServiceBuilder;
use TwentytwoLabs\Api\Service\Pagination\Provider\PaginationProviderInterface;

/**
 * Class ApiServiceBuilderTest.
 */
class ApiServiceBuilderTest extends TestCase
{
    public function testShouldBuildAnApiService()
    {
        $schemaFixture = __DIR__.'/fixtures/httpbin.yml';
        $apiService = ApiServiceBuilder::create()->build('file://'.$schemaFixture);

        $this->assertInstanceOf(ApiService::class, $apiService);
    }

    public function testShouldBuildAnApiServiceButWeDisableChecks()
    {
        $schemaFile = 'file://fake-schema.yml';

        $schema = $this->createMock(Schema::class);
        $schema->expects($this->never())->method('getSchemes');
        $schema->expects($this->never())->method('getHost');

        $item = $this->createMock(CacheItemInterface::class);
        $item->expects($this->once())->method('isHit')->willReturn(true);
        $item->expects($this->once())->method('get')->willReturn($schema);

        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cache
            ->expects($this->once())
            ->method('getItem')
            ->with('3f470a326a5926a2e323aaadd767c0e64302a080')
            ->willReturn($item)
        ;

        $client = $this->createMock(HttpClient::class);
        $messageFactory = $this->createMock(MessageFactory::class);

        $uri = $this->createMock(UriInterface::class);

        $uriFactory = $this->createMock(UriFactory::class);
        $uriFactory->expects($this->once())->method('createUri')->willReturn($uri);

        $serializer = $this->createMock(SerializerInterface::class);
        $encoder = $this->createMock(EncoderInterface::class);
        $denormalizer = $this->createMock(NormalizerInterface::class);
        $paginationProvider = $this->createMock(PaginationProviderInterface::class);

        ApiServiceBuilder::create()
            ->withCacheProvider($cache)
            ->withHttpClient($client)
            ->withMessageFactory($messageFactory)
            ->withUriFactory($uriFactory)
            ->withSerializer($serializer)
            ->withEncoder($encoder)
            ->withDenormalizer($denormalizer)
            ->withPaginationProvider($paginationProvider)
            ->withBaseUri('http://example.org')
            ->disableRequestValidation()
            ->returnResponse()
            ->build($schemaFile)
        ;
    }

    public function testShouldBuildAnApiServiceButWeEnableChecks()
    {
        $schemaFile = 'file://fake-schema.yml';

        $schema = $this->createMock(Schema::class);
        $schema->expects($this->never())->method('getSchemes');
        $schema->expects($this->never())->method('getHost');

        $item = $this->createMock(CacheItemInterface::class);
        $item->expects($this->once())->method('isHit')->willReturn(true);
        $item->expects($this->once())->method('get')->willReturn($schema);

        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cache
            ->expects($this->once())
            ->method('getItem')
            ->with('3f470a326a5926a2e323aaadd767c0e64302a080')
            ->willReturn($item)
        ;

        $client = $this->createMock(HttpClient::class);
        $messageFactory = $this->createMock(MessageFactory::class);

        $uri = $this->createMock(UriInterface::class);

        $uriFactory = $this->createMock(UriFactory::class);
        $uriFactory->expects($this->once())->method('createUri')->willReturn($uri);

        $serializer = $this->createMock(SerializerInterface::class);
        $encoder = $this->createMock(EncoderInterface::class);
        $denormalizer = $this->createMock(NormalizerInterface::class);
        $paginationProvider = $this->createMock(PaginationProviderInterface::class);

        ApiServiceBuilder::create()
            ->withCacheProvider($cache)
            ->withHttpClient($client)
            ->withMessageFactory($messageFactory)
            ->withUriFactory($uriFactory)
            ->withSerializer($serializer)
            ->withEncoder($encoder)
            ->withDenormalizer($denormalizer)
            ->withPaginationProvider($paginationProvider)
            ->withBaseUri('http://example.org')
            ->enableResponseValidation()
            ->returnResponse()
            ->build($schemaFile)
        ;
    }

    public function testShouldBuildAnApiServiceFromCache()
    {
        $schemaFile = 'file://fake-schema.yml';

        $schema = $this->createMock(Schema::class);
        $schema->expects($this->exactly(2))->method('getSchemes')->willReturn(['https']);
        $schema->expects($this->once())->method('getHost')->willReturn('domain.tld');

        $item = $this->createMock(CacheItemInterface::class);
        $item->expects($this->once())->method('isHit')->willReturn(true);
        $item->expects($this->once())->method('get')->willReturn($schema);

        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cache
            ->expects($this->once())
            ->method('getItem')
            ->with('3f470a326a5926a2e323aaadd767c0e64302a080')
            ->willReturn($item)
        ;

        $client = $this->createMock(HttpClient::class);
        $messageFactory = $this->createMock(MessageFactory::class);

        $uri = $this->createMock(UriInterface::class);

        $uriFactory = $this->createMock(UriFactory::class);
        $uriFactory->expects($this->once())->method('createUri')->willReturn($uri);

        $serializer = $this->createMock(SerializerInterface::class);
        $encoder = $this->createMock(EncoderInterface::class);
        $denormalizer = $this->createMock(NormalizerInterface::class);
        $paginationProvider = $this->createMock(PaginationProviderInterface::class);

        ApiServiceBuilder::create()
            ->withCacheProvider($cache)
            ->withHttpClient($client)
            ->withMessageFactory($messageFactory)
            ->withUriFactory($uriFactory)
            ->withSerializer($serializer)
            ->withEncoder($encoder)
            ->withDenormalizer($denormalizer)
            ->withPaginationProvider($paginationProvider)
            ->build($schemaFile)
        ;
    }
}
