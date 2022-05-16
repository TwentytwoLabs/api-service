<?php

declare(strict_types=1);

namespace TwentytwoLabs\Api\Service\Pagination\Provider;

use Psr\Http\Message\ResponseInterface;
use TwentytwoLabs\Api\Definition\ResponseDefinition;
use TwentytwoLabs\Api\Service\Pagination\Pagination;
use TwentytwoLabs\Api\Service\Pagination\PaginationLinks;

/**
 * Class PaginationHeader.
 */
class PaginationHeader implements PaginationProviderInterface
{
    /**
     * @var array
     */
    public const DEFAULT_PAGINATION_HEADERS = [
        // current page
        'page' => 'X-Page',
        // number of items per page
        'perPage' => 'X-Per-Page',
        // number of items available in total
        'totalItems' => 'X-Total-Items',
        // number of pages available
        'totalPages' => 'X-Total-Pages',
    ];

    private array $paginationHeaders;

    public function __construct(array $config = [])
    {
        foreach (self::DEFAULT_PAGINATION_HEADERS as $name => $headerName) {
            if (isset($config[$name])) {
                $headerName = $config[$name];
            }
            $this->paginationHeaders[$name] = $headerName;
        }
    }

    public function getPagination(array &$data, ResponseInterface $response, ResponseDefinition $responseDefinition): Pagination
    {
        $paginationLinks = null;
        if ($response->hasHeader('Link')) {
            $links = self::parseHeaderLinks($response->getHeader('Link'));
            $paginationLinks = new PaginationLinks(
                $links['first'],
                $links['last'],
                $links['next'],
                $links['prev']
            );
        }

        return new Pagination(
            (int) $response->getHeaderLine($this->paginationHeaders['page']),
            (int) $response->getHeaderLine($this->paginationHeaders['perPage']),
            (int) $response->getHeaderLine($this->paginationHeaders['totalItems']),
            (int) $response->getHeaderLine($this->paginationHeaders['totalPages']),
            $paginationLinks
        );
    }

    public function supportPagination(array $data, ResponseInterface $response, ResponseDefinition $responseDefinition): bool
    {
        foreach ($this->paginationHeaders as $headerName) {
            if (empty($response->getHeaderLine($headerName))) {
                return false;
            }
        }

        return true;
    }

    private static function parseHeaderLinks(array $headerLinks): array
    {
        $links = ['first' => '', 'last' => '', 'next' => null, 'prev' => null];

        foreach ($headerLinks as $headerLink) {
            preg_match('/rel="([^"]+)"/', $headerLink, $matches);

            if (2 === \count($matches) && in_array($matches[1], ['next', 'prev', 'first', 'last'])) {
                $parts = explode(';', $headerLink);
                $url = trim($parts[0], ' <>');
                $links[$matches[1]] = $url;
            }
        }

        return $links;
    }
}
