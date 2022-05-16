<?php

declare(strict_types=1);

namespace TwentytwoLabs\Api\Service\Tests\Pagination;

use PHPUnit\Framework\TestCase;
use TwentytwoLabs\Api\Service\Pagination\Pagination;
use TwentytwoLabs\Api\Service\Pagination\PaginationLinks;

/**
 * Class PaginationTest.
 */
class PaginationTest extends TestCase
{
    public function testShouldProvidePaginationMetadata()
    {
        $pagination = new Pagination(2, 20, 100, 5);

        $this->assertSame(2, $pagination->getPage());
        $this->assertSame(20, $pagination->getPerPage());
        $this->assertSame(100, $pagination->getTotalItems());
        $this->assertSame(5, $pagination->getTotalPages());
        $this->assertFalse($pagination->hasLinks());
    }

    public function testShouldProvidePaginationLinks()
    {
        $links = $this->createMock(PaginationLinks::class);
        $pagination = new Pagination(2, 20, 100, 5, $links);

        $this->assertInstanceOf(PaginationLinks::class, $pagination->getLinks());
    }
}
