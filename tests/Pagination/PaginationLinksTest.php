<?php

declare(strict_types=1);

namespace TwentytwoLabs\Api\Service\Tests\Pagination;

use PHPUnit\Framework\TestCase;
use TwentytwoLabs\Api\Service\Pagination\PaginationLinks;

/**
 * Class PaginationLinksTest.
 */
class PaginationLinksTest extends TestCase
{
    public function testShouldProvidePaginationFirstAndLastLinks()
    {
        $links = new PaginationLinks(
            'http://domain.tld?page=1',
            'http://domain.tld?page=5'
        );

        $this->assertSame('http://domain.tld?page=1', $links->getFirst());
        $this->assertSame('http://domain.tld?page=5', $links->getLast());
        $this->assertFalse($links->hasNext());
        $this->assertFalse($links->hasPrev());
    }

    public function testShouldProvidePaginationNextAndPrevLinks()
    {
        $links = new PaginationLinks(
            'http://domain.tld?page=1',
            'http://domain.tld?page=5',
            'http://domain.tld?page=3',
            'http://domain.tld?page=2'
        );

        $this->assertTrue($links->hasNext());
        $this->assertTrue($links->hasPrev());
        $this->assertSame('http://domain.tld?page=3', $links->getNext());
        $this->assertSame('http://domain.tld?page=2', $links->getPrev());
    }
}
