<?php

declare(strict_types=1);

namespace TwentytwoLabs\Api\Service\Tests\Denormalizer;

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use TwentytwoLabs\Api\Service\Denormalizer\ErrorDenormalizer;
use TwentytwoLabs\Api\Service\Resource\Error;
use TwentytwoLabs\Api\Service\Resource\ErrorInterface;
use TwentytwoLabs\Api\Service\Resource\ResourceInterface;

/**
 * Class ErrorDenormalizerTest.
 *
 * @codingStandardsIgnoreFile
 *
 * @SuppressWarnings(PHPMD)
 */
class ErrorDenormalizerTest extends TestCase
{
    public function testShouldDenormalizeErrorWithOutViolations()
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->once())->method('getStatusCode')->willReturn(500);
        $response->expects($this->once())->method('getReasonPhrase')->willReturn('Internal Server Error');

        $denormalizer = $this->getDenormalizer();
        $error = $denormalizer->denormalize([], ErrorDenormalizer::class, null, ['response' => $response]);

        $this->assertInstanceOf(Error::class, $error);
        $this->assertSame('Internal Server Error', $error->getMessage());
        $this->assertSame([], $error->getViolations());
        $this->assertSame(500, $error->getCode());
    }

    public function testShouldDenormalizeErrorWithViolations()
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->once())->method('getStatusCode')->willReturn(400);
        $response->expects($this->once())->method('getReasonPhrase')->willReturn('Bad Request');

        $violations = [
            [
                'propertyPath' => 'title',
                'message' => 'assert.not-blank.title',
                'code' => 'c1051bb4-d103-4f74-8988-acbcafc7fdc3',
            ],
        ];

        $denormalizer = $this->getDenormalizer();
        $error = $denormalizer->denormalize(
            ['violations' => $violations],
            ErrorDenormalizer::class, null,
            ['response' => $response]
        );

        $this->assertInstanceOf(Error::class, $error);
        $this->assertSame('Bad Request', $error->getMessage());
        $this->assertSame($violations, $error->getViolations());
        $this->assertSame(400, $error->getCode());
    }

    public function testShouldSupportsDenormalization()
    {
        $denormalizer = $this->getDenormalizer();
        $this->assertTrue($denormalizer->supportsDenormalization([], ErrorInterface::class));
    }

    public function testShouldNotSupportsDenormalization()
    {
        $denormalizer = $this->getDenormalizer();
        $this->assertFalse($denormalizer->supportsDenormalization([], ResourceInterface::class));
    }

    private function getDenormalizer(): ErrorDenormalizer
    {
        return new ErrorDenormalizer();
    }
}
