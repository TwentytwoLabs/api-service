<?php

declare(strict_types=1);

namespace TwentytwoLabs\Api\Service\Tests\Pagination\Provider;

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use TwentytwoLabs\Api\Definition\ResponseDefinition;
use TwentytwoLabs\Api\Service\Pagination\Pagination;
use TwentytwoLabs\Api\Service\Pagination\PaginationLinks;
use TwentytwoLabs\Api\Service\Pagination\Provider\PaginationHeader;

/**
 * Class PaginationHeaderTest.
 */
class PaginationHeaderTest extends TestCase
{
    public function testShouldSupportPaginationHeader()
    {
        $response = $this->createMock(ResponseInterface::class);
        $response
            ->expects($this->exactly(4))
            ->method('getHeaderLine')
            ->withConsecutive(['X-Page'], ['X-Per-Page'], ['X-Total-Items'], ['X-Total-Pages'])
            ->willReturnOnConsecutiveCalls('1', '10', '100', '10')
        ;

        $definition = $this->createMock(ResponseDefinition::class);
        $provider = new PaginationHeader();

        $this->assertTrue($provider->supportPagination([], $response, $definition));
    }

    public function testShouldNotSupportPaginationHeaderBecauseHeaderIsEmpty()
    {
        $response = $this->createMock(ResponseInterface::class);
        $response
            ->expects($this->exactly(4))
            ->method('getHeaderLine')
            ->withConsecutive(['X-Page'], ['X-Per-Page'], ['X-Total-Items'], ['X-Total-Pages'])
            ->willReturnOnConsecutiveCalls('1', '10', '100', '')
        ;

        $definition = $this->createMock(ResponseDefinition::class);
        $provider = new PaginationHeader();

        $this->assertFalse($provider->supportPagination([], $response, $definition));
    }

    public function testShouldProvidePaginationUsingResponseHeaders()
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->once())->method('hasHeader')->with('Link')->willReturn(false);
        $response
            ->expects($this->exactly(4))
            ->method('getHeaderLine')
            ->withConsecutive(['X-Page'], ['X-Per-Page'], ['X-Total-Items'], ['X-Total-Pages'])
            ->willReturnOnConsecutiveCalls('1', '10', '100', '10')
        ;
        $data = [];

        $definition = $this->createMock(ResponseDefinition::class);

        $provider = new PaginationHeader();
        $pagination = $provider->getPagination($data, $response, $definition);

        $this->assertInstanceOf(Pagination::class, $pagination);
        $this->assertSame(1, $pagination->getPage());
        $this->assertSame(10, $pagination->getPerPage());
        $this->assertSame(100, $pagination->getTotalItems());
        $this->assertSame(10, $pagination->getTotalPages());
        $this->assertSame(null, $pagination->getLinks());
    }

    public function testShouldAllowPaginationHeaderKeyOverride()
    {
        $config = [
            'page' => 'X-Pagination-Page',
            'perPage' => 'X-Pagination-Per-Page',
            'totalItems' => 'X-Pagination-Total-Items',
            'totalPages' => 'X-Pagination-Total-Pages',
        ];

        $data = [];

        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->once())->method('hasHeader')->with('Link')->willReturn(false);
        $response
            ->expects($this->exactly(4))
            ->method('getHeaderLine')
            ->withConsecutive(['X-Pagination-Page'], ['X-Pagination-Per-Page'], ['X-Pagination-Total-Items'], ['X-Pagination-Total-Pages'])
            ->willReturnOnConsecutiveCalls('1', '10', '100', '10')
        ;

        $definition = $this->createMock(ResponseDefinition::class);

        $provider = new PaginationHeader($config);
        $pagination = $provider->getPagination($data, $response, $definition);

        $this->assertInstanceOf(Pagination::class, $pagination);
        $this->assertSame(1, $pagination->getPage());
        $this->assertSame(10, $pagination->getPerPage());
        $this->assertSame(100, $pagination->getTotalItems());
        $this->assertSame(10, $pagination->getTotalPages());
        $this->assertSame(null, $pagination->getLinks());
    }

    public function testShouldProvidePaginationLinksWhenThereAreNoLinks()
    {
        $linkHeader = [];

        $response = $this->createMock(ResponseInterface::class);
        $response
            ->expects($this->exactly(4))
            ->method('getHeaderLine')
            ->withConsecutive(['X-Page'], ['X-Per-Page'], ['X-Total-Items'], ['X-Total-Pages'])
            ->willReturnOnConsecutiveCalls('1', '10', '10', '1')
        ;
        $response->expects($this->once())->method('hasHeader')->with('Link')->willReturn(true);
        $response->expects($this->once())->method('getHeader')->with('Link')->willReturn($linkHeader);

        $data = [];

        $definition = $this->createMock(ResponseDefinition::class);

        $provider = new PaginationHeader();
        $pagination = $provider->getPagination($data, $response, $definition);
        $this->assertSame(1, $pagination->getPage());
        $this->assertSame(10, $pagination->getPerPage());
        $this->assertSame(10, $pagination->getTotalItems());
        $this->assertSame(1, $pagination->getTotalPages());

        $paginationLinks = $pagination->getLinks();
        $this->assertInstanceOf(PaginationLinks::class, $paginationLinks);
        $this->assertSame('', $paginationLinks->getFirst());
        $this->assertSame('', $paginationLinks->getLast());
        $this->assertSame(null, $paginationLinks->getNext());
        $this->assertSame(null, $paginationLinks->getPrev());
    }

    public function testShouldProvidePaginationLinksWhenFirstAndLastAreOnlyDefined()
    {
        $linkHeader = [
            '<http://domain.tld?page=1>; rel="first"',
            '<http://domain.tld?page=1>; rel="last"',
        ];

        $response = $this->createMock(ResponseInterface::class);
        $response
            ->expects($this->exactly(4))
            ->method('getHeaderLine')
            ->withConsecutive(['X-Page'], ['X-Per-Page'], ['X-Total-Items'], ['X-Total-Pages'])
            ->willReturnOnConsecutiveCalls('1', '10', '10', '1')
        ;
        $response->expects($this->once())->method('hasHeader')->with('Link')->willReturn(true);
        $response->expects($this->once())->method('getHeader')->with('Link')->willReturn($linkHeader);

        $data = [];

        $definition = $this->createMock(ResponseDefinition::class);

        $provider = new PaginationHeader();
        $pagination = $provider->getPagination($data, $response, $definition);
        $this->assertSame(1, $pagination->getPage());
        $this->assertSame(10, $pagination->getPerPage());
        $this->assertSame(10, $pagination->getTotalItems());
        $this->assertSame(1, $pagination->getTotalPages());

        $paginationLinks = $pagination->getLinks();
        $this->assertInstanceOf(PaginationLinks::class, $paginationLinks);
        $this->assertSame('http://domain.tld?page=1', $paginationLinks->getFirst());
        $this->assertSame('http://domain.tld?page=1', $paginationLinks->getLast());
        $this->assertSame(null, $paginationLinks->getNext());
        $this->assertSame(null, $paginationLinks->getPrev());
    }

    public function testShouldProvidePaginationLinks()
    {
        $linkHeader = [
            '<http://domain.tld?page=1>; rel="first"',
            '<http://domain.tld?page=10>; rel="last"',
            '<http://domain.tld?page=4>; rel="next"',
            '<http://domain.tld?page=2>; rel="prev"',
        ];

        $response = $this->createMock(ResponseInterface::class);
        $response
            ->expects($this->exactly(4))
            ->method('getHeaderLine')
            ->withConsecutive(['X-Page'], ['X-Per-Page'], ['X-Total-Items'], ['X-Total-Pages'])
            ->willReturnOnConsecutiveCalls('1', '10', '100', '10')
        ;
        $response->expects($this->once())->method('hasHeader')->with('Link')->willReturn(true);
        $response->expects($this->once())->method('getHeader')->with('Link')->willReturn($linkHeader);

        $data = [];

        $definition = $this->createMock(ResponseDefinition::class);

        $provider = new PaginationHeader();
        $pagination = $provider->getPagination($data, $response, $definition);
        $this->assertSame(1, $pagination->getPage());
        $this->assertSame(10, $pagination->getPerPage());
        $this->assertSame(100, $pagination->getTotalItems());
        $this->assertSame(10, $pagination->getTotalPages());

        $paginationLinks = $pagination->getLinks();
        $this->assertInstanceOf(PaginationLinks::class, $paginationLinks);
        $this->assertSame('http://domain.tld?page=1', $paginationLinks->getFirst());
        $this->assertSame('http://domain.tld?page=10', $paginationLinks->getLast());
        $this->assertSame('http://domain.tld?page=4', $paginationLinks->getNext());
        $this->assertSame('http://domain.tld?page=2', $paginationLinks->getPrev());
    }
}
