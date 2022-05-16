<?php

declare(strict_types=1);

namespace TwentytwoLabs\Api\Service\Pagination\Provider;

use Psr\Http\Message\ResponseInterface;
use TwentytwoLabs\Api\Definition\ResponseDefinition;
use TwentytwoLabs\Api\Service\Pagination\Pagination;

/**
 * Interface PaginationProviderInterface.
 */
interface PaginationProviderInterface
{
    public function supportPagination(array $data, ResponseInterface $response, ResponseDefinition $responseDefinition): bool;

    public function getPagination(array &$data, ResponseInterface $response, ResponseDefinition $responseDefinition): Pagination;
}
