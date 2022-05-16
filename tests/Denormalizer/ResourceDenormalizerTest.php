<?php

declare(strict_types=1);

namespace TwentytwoLabs\Api\Service\Tests\Denormalizer;

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;
use TwentytwoLabs\Api\Definition\ResponseDefinition;
use TwentytwoLabs\Api\Service\Denormalizer\ResourceDenormalizer;
use TwentytwoLabs\Api\Service\Pagination\Pagination;
use TwentytwoLabs\Api\Service\Pagination\Provider\PaginationProviderInterface;
use TwentytwoLabs\Api\Service\Resource\Collection;
use TwentytwoLabs\Api\Service\Resource\Item;
use TwentytwoLabs\Api\Service\Resource\ResourceInterface;

/**
 * Class ResourceDenormalizerTest.
 */
class ResourceDenormalizerTest extends TestCase
{
    public function testShouldSupportResourceType()
    {
        $paginationProvider = $this->createMock(PaginationProviderInterface::class);
        $denormalizer = new ResourceDenormalizer($paginationProvider);

        $this->assertTrue($denormalizer->supportsDenormalization([], ResourceInterface::class));
    }

    public function testShouldProvideAResourceOfTypeItem()
    {
        $response = $this->createMock(ResponseInterface::class);

        $request = $this->createMock(RequestInterface::class);

        $responseDefinition = $this->createMock(ResponseDefinition::class);
        $responseDefinition->expects($this->once())->method('hasBodySchema')->willReturn(true);
        $responseDefinition->expects($this->once())->method('getBodySchema')->willReturn((object) ['type' => 'object']);

        $paginationProvider = $this->createMock(PaginationProviderInterface::class);
        $paginationProvider->expects($this->never())->method('supportPagination');

        $denormalizer = new ResourceDenormalizer($paginationProvider);
        $resource = $denormalizer->denormalize(
            ['foo' => 'bar'],
            ResourceInterface::class,
            null,
            ['response' => $response, 'responseDefinition' => $responseDefinition, 'request' => $request]
        );

        $this->assertInstanceOf(Item::class, $resource);
    }

    public function testShouldThrowAnExceptionWhenNoResponseSchemaIsDefinedInTheResponseDefinition()
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(
            'Cannot transform the response into a resource. '.
            'You need to provide a schema for response 200 in GET /foo'
        );

        $requestPath = '/foo';

        $uri = $this->createMock(UriInterface::class);
        $uri->expects($this->once())->method('getPath')->willReturn($requestPath);

        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->once())->method('getStatusCode')->willReturn(200);

        $request = $this->createMock(RequestInterface::class);
        $request->expects($this->once())->method('getUri')->willreturn($uri);
        $request->expects($this->once())->method('getMethod')->willReturn('GET');

        $responseDefinition = $this->createMock(ResponseDefinition::class);
        $responseDefinition->expects($this->once())->method('hasBodySchema')->willReturn(false);

        $paginationProvider = $this->createMock(PaginationProviderInterface::class);

        $denormalizer = new ResourceDenormalizer($paginationProvider);
        $denormalizer->denormalize(
            [],
            ResourceInterface::class,
            null,
            ['response' => $response, 'responseDefinition' => $responseDefinition, 'request' => $request]
        );
    }

    public function testShouldProvideAResourceOfTypeCollection()
    {
        $data = [
            ['foo' => 'bar'],
        ];

        $response = $this->createMock(ResponseInterface::class);

        $request = $this->createMock(RequestInterface::class);

        $responseDefinition = $this->createMock(ResponseDefinition::class);
        $responseDefinition->expects($this->once())->method('hasBodySchema')->willReturn(true);
        $responseDefinition->expects($this->once())->method('getBodySchema')->willReturn((object) ['type' => 'array']);

        $paginationProvider = $this->createMock(PaginationProviderInterface::class);
        $paginationProvider->expects($this->once())->method('supportPagination')->with($data, $response, $responseDefinition)->willReturn(false);

        $denormalizer = new ResourceDenormalizer($paginationProvider);
        $resource = $denormalizer->denormalize(
            $data,
            ResourceInterface::class,
            null,
            ['response' => $response, 'responseDefinition' => $responseDefinition, 'request' => $request]
        );

        $this->assertInstanceOf(Collection::class, $resource);
    }

    public function testShouldProvideAResourceOfTypeCollectionWithPagination()
    {
        $data = [
            ['foo' => 'bar'],
        ];

        $response = $this->createMock(ResponseInterface::class);

        $request = $this->createMock(RequestInterface::class);

        $responseDefinition = $this->createMock(ResponseDefinition::class);
        $responseDefinition->expects($this->once())->method('hasBodySchema')->willReturn(true);
        $responseDefinition->expects($this->once())->method('getBodySchema')->willReturn((object) ['type' => 'array']);

        $pagination = $this->createMock(Pagination::class);

        $paginationProvider = $this->createMock(PaginationProviderInterface::class);
        $paginationProvider->expects($this->once())->method('supportPagination')->with($data, $response, $responseDefinition)->willReturn(true);
        $paginationProvider->expects($this->once())->method('getPagination')->with($data, $response, $responseDefinition)->willReturn($pagination);

        $denormalizer = new ResourceDenormalizer($paginationProvider);
        $resource = $denormalizer->denormalize(
            $data,
            ResourceInterface::class,
            null,
            ['response' => $response, 'responseDefinition' => $responseDefinition, 'request' => $request]
        );

        $this->assertInstanceOf(Collection::class, $resource);
        $this->assertSame($pagination, $resource->getPagination());
    }

    public function testCanExtractTypeFromAnAllOfSchema()
    {
        $jsonSchema = (object) [
            'allOf' => [
                (object) ['type' => 'object'],
            ],
        ];

        $response = $this->createMock(ResponseInterface::class);

        $request = $this->createMock(RequestInterface::class);

        $responseDefinition = $this->createMock(ResponseDefinition::class);
        $responseDefinition->expects($this->once())->method('hasBodySchema')->willReturn(true);
        $responseDefinition->expects($this->once())->method('getBodySchema')->willReturn($jsonSchema);

        $paginationProvider = $this->createMock(PaginationProviderInterface::class);
        $paginationProvider->expects($this->never())->method('supportPagination');

        $denormalizer = new ResourceDenormalizer($paginationProvider);
        $resource = $denormalizer->denormalize(
            ['foo' => 'bar'],
            ResourceInterface::class,
            null,
            ['response' => $response, 'responseDefinition' => $responseDefinition, 'request' => $request]
        );

        $this->assertInstanceOf(Item::class, $resource);
        $this->assertSame(['headers' => null], $resource->getMeta());
    }

    public function testShouldThrowAnExceptionWhenSchemaTypeCannotBeExtracted()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot extract type from schema');

        $jsonSchema = (object) ['invalid' => 'invalid'];

        $response = $this->createMock(ResponseInterface::class);
        $request = $this->createMock(RequestInterface::class);

        $responseDefinition = $this->createMock(ResponseDefinition::class);
        $responseDefinition->expects($this->once())->method('hasBodySchema')->willReturn(true);
        $responseDefinition->expects($this->once())->method('getBodySchema')->willReturn($jsonSchema);

        $paginationProvider = $this->createMock(PaginationProviderInterface::class);
        $paginationProvider->expects($this->never())->method('supportPagination');

        $denormalizer = new ResourceDenormalizer($paginationProvider);
        $denormalizer->denormalize(
            ['foo' => 'bar'],
            ResourceInterface::class,
            null,
            ['response' => $response, 'responseDefinition' => $responseDefinition, 'request' => $request]
        );
    }
}
