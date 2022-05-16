<?php

declare(strict_types=1);

namespace TwentytwoLabs\Api\Service\Tests\Resource;

use PHPUnit\Framework\TestCase;
use TwentytwoLabs\Api\Service\Pagination\Pagination;
use TwentytwoLabs\Api\Service\Resource\Collection;
use TwentytwoLabs\Api\Service\Resource\ResourceInterface;

/**
 * Class CollectionTest.
 */
class CollectionTest extends TestCase
{
    public function testShouldBeAResource()
    {
        $resource = new Collection([], []);

        $this->assertInstanceOf(ResourceInterface::class, $resource);
    }

    public function testShouldProvideDataAndMeta()
    {
        $data = [['foo' => 'bar']];
        $meta = ['headers' => ['bat' => 'baz']];
        $resource = new Collection($data, $meta);

        $this->assertSame($data, $resource->getData());
        $this->assertSame($meta, $resource->getMeta());
        $this->assertFalse($resource->hasPagination());
    }

    public function testShouldProvideAPagination()
    {
        $pagination = new Pagination(1, 1, 1, 1);
        $resource = new Collection([], [], $pagination);

        $this->assertSame($pagination, $resource->getPagination());
    }

    public function testShouldBeTraversable()
    {
        $data = [
            ['value' => 'foo'],
            ['value' => 'bar'],
        ];

        $resource = new Collection($data, []);

        $this->assertInstanceOf(\Traversable::class, $resource);
        $this->assertContains($data[0], $resource);
        $this->assertContains($data[1], $resource);
    }
}
