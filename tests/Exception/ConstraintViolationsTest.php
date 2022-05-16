<?php

declare(strict_types=1);

namespace TwentytwoLabs\Api\Service\Tests\Exception;

use PHPUnit\Framework\TestCase;
use TwentytwoLabs\Api\Service\Exception\ApiServiceError;
use TwentytwoLabs\Api\Service\Exception\ConstraintViolations;
use TwentytwoLabs\Api\Validator\ConstraintViolation;

/**
 * Class ConstraintViolationsTest.
 *
 * @codingStandardsIgnoreFile
 *
 * @SuppressWarnings(PHPMD)
 */
class ConstraintViolationsTest extends TestCase
{
    public function testShouldExtendApiServiceError()
    {
        $exception = new ConstraintViolations([]);

        $this->assertInstanceOf(ApiServiceError::class, $exception);
    }

    public function testShouldProvideTheListOfViolations()
    {
        $violationFoo = $this->createMock(ConstraintViolation::class);
        $violationFoo->expects($this->exactly(2))->method('getProperty')->willReturn('foo');
        $violationFoo->expects($this->exactly(2))->method('getMessage')->willReturn('bar is not a string');
        $violationFoo->expects($this->exactly(2))->method('getConstraint')->willReturn('');
        $violationFoo->expects($this->exactly(2))->method('getLocation')->willReturn('foo');

        $violationBar = $this->createMock(ConstraintViolation::class);
        $violationBar->expects($this->exactly(2))->method('getProperty')->willReturn('bar');
        $violationBar->expects($this->exactly(2))->method('getMessage')->willReturn('foo is not a string');
        $violationBar->expects($this->exactly(2))->method('getConstraint')->willReturn('');
        $violationBar->expects($this->exactly(2))->method('getLocation')->willReturn('body');

        $exception = new ConstraintViolations([$violationFoo, $violationBar]);

        $this->assertSame([$violationFoo, $violationBar], $exception->getViolations());

        $this->assertSame("Request constraint violations:\n[property]: foo\n[message]: bar is not a string\n[constraint]: \n[location]: foo\n\n[property]: bar\n[message]: foo is not a string\n[constraint]: \n[location]: body\n\n", (string) $exception);
    }
}
