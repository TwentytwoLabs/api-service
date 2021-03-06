<?php

declare(strict_types=1);

namespace TwentytwoLabs\Api\Service\Resource;

/**
 * Interface ErrorInterface.
 */
interface ErrorInterface
{
    public function getCode(): int;

    public function getMessage(): string;

    public function getViolations(): array;
}
