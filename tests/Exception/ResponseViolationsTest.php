<?php

declare(strict_types=1);

namespace TwentytwoLabs\Api\Service\Tests\Exception;

use PHPUnit\Framework\TestCase;
use TwentytwoLabs\Api\Service\Exception\ConstraintViolations;
use TwentytwoLabs\Api\Service\Exception\ResponseViolations;

/**
 * Class ResponseViolationsTest.
 */
class ResponseViolationsTest extends TestCase
{
    public function testShouldExtendConstraintViolations()
    {
        $exception = new ResponseViolations([]);

        $this->assertInstanceOf(ConstraintViolations::class, $exception);
    }
}
