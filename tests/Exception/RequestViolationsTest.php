<?php

declare(strict_types=1);

namespace TwentytwoLabs\Api\Service\Tests\Exception;

use PHPUnit\Framework\TestCase;
use TwentytwoLabs\Api\Service\Exception\ConstraintViolations;
use TwentytwoLabs\Api\Service\Exception\RequestViolations;

/**
 * Class RequestViolationsTest.
 */
class RequestViolationsTest extends TestCase
{
    public function testShouldExtendConstraintViolations()
    {
        $exception = new RequestViolations([]);

        $this->assertInstanceOf(ConstraintViolations::class, $exception);
    }
}
