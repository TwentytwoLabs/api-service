<?php

declare(strict_types=1);

namespace TwentytwoLabs\Api\Service\Tests\Resource;

use PHPUnit\Framework\TestCase;
use TwentytwoLabs\Api\Service\Resource\Item;
use TwentytwoLabs\Api\Service\Resource\ResourceInterface;

/**
 * Class ItemTest.
 */
class ItemTest extends TestCase
{
    public function testShouldBeAResource()
    {
        $resource = new Item([], []);

        $this->assertInstanceOf(ResourceInterface::class, $resource);
    }

    public function testShouldProvideDataAndMeta()
    {
        $data = ['foo' => 'bar'];
        $meta = ['headers' => ['bat' => 'baz']];
        $resource = new Item($data, $meta);

        $this->assertSame($data, $resource->getData());
        $this->assertSame($meta, $resource->getMeta());
    }
}
