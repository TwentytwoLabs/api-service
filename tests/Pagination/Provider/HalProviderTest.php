<?php

declare(strict_types=1);

namespace TwentytwoLabs\Api\Service\Tests\Pagination\Provider;

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use TwentytwoLabs\Api\Definition\ResponseDefinition;
use TwentytwoLabs\Api\Service\Pagination\Pagination;
use TwentytwoLabs\Api\Service\Pagination\PaginationLinks;
use TwentytwoLabs\Api\Service\Pagination\Provider\HalProvider;

/**
 * Class HalProviderTest.
 */
class HalProviderTest extends TestCase
{
    private ResponseInterface $response;
    private ResponseDefinition $responseDefinition;
    private array $config;

    protected function setUp(): void
    {
        $this->response = $this->createMock(ResponseInterface::class);
        $this->responseDefinition = $this->createMock(ResponseDefinition::class);
        $this->config = [
            'page' => '_links.self.href.page',
            'perPage' => 'itemsPerPage',
            'totalItems' => 'totalItems',
            'totalPages' => '_links.last.href.page',
        ];
    }

    public function testShouldNotSupportPaginationWhenResponseIsEmpty()
    {
        $data = [];
        $provider = new HalProvider($this->config);
        $this->assertFalse($provider->supportPagination($data, $this->response, $this->responseDefinition));
    }

    /**
     * @dataProvider dataProviderItNotSupportPaginationWhenProblemFromLinks
     */
    public function testShouldNotSupportPaginationWhenProblemFromLinks(array $links)
    {
        $data = [
            '_links' => $links,
            'itemsPerPage' => 10,
            'totalItems' => 20,
            '_embedded' => ['item' => []],
        ];
        $provider = new HalProvider($this->config);
        $this->assertFalse($provider->supportPagination($data, $this->response, $this->responseDefinition));
    }

    public function dataProviderItNotSupportPaginationWhenProblemFromLinks(): array
    {
        return [
            [
                [],
            ],
            [
                [
                    'first' => ['href' => 'http://example.org?page=1&foo=bar'],
                    'last' => ['href' => 'http://example.org?page=2&foo=bar'],
                ],
            ],
            [
                [
                    'self' => ['href' => 'http://example.org?page=1&foo=bar'],
                    'first' => ['href' => 'http://example.org?page=1&foo=bar'],
                ],
            ],
            [
                [
                    'last' => ['href' => 'http://example.org?page=2&foo=bar'],
                ],
            ],
        ];
    }

    public function testShouldNotSupportPaginationWhenThereAreNotEmbeddedField()
    {
        $data = [
            '_links' => [
                'self' => ['href' => 'http://example.org?page=1'],
                'first' => ['href' => 'http://example.org?page=1'],
                'last' => ['href' => 'http://example.org?page=2'],
            ],
            'itemsPerPage' => 10,
            'totalItems' => 20,
        ];

        $provider = new HalProvider($this->config);
        $this->assertFalse($provider->supportPagination($data, $this->response, $this->responseDefinition));
    }

    public function testShouldSupportPaginationWhenThereAreNoData()
    {
        $data = [
            'itemsPerPage' => 10,
            'totalItems' => 0,
            '_embedded' => [],
        ];

        $provider = new HalProvider($this->config);
        $this->assertTrue($provider->supportPagination($data, $this->response, $this->responseDefinition));
    }

    public function testShouldSupportPagination()
    {
        $data = [
            '_links' => [
                'self' => [
                    'href' => '/users?page=1&foo=bar',
                ],
                'item' => [
                    [
                        'href' => '/users/70081b35-3de9-4112-9438-f87d0f12bdde',
                    ],
                    [
                        'href' => '/users/d5f23798-9475-4fdf-8e6c-b0af4a3f5b71',
                    ],
                    [
                        'href' => '/users/YmVuamFtaW4uYmxpbkBnbWFpbC5jb20=',
                    ],
                    [
                        'href' => '/users/bGF1cmUuZ29tZXNAZ21haWwuY29t',
                    ],
                    [
                        'href' => '/users/dGhpZXJyeS5sZWJyZXRvbkBnbWFpbC5jb20=',
                    ],
                    [
                        'href' => '/users/bWFkZWxlaW5lLmhhbW9uQGdtYWlsLmNvbQ==',
                    ],
                    [
                        'href' => '/users/cGF0cmljay5iZXJnZXJAZ21haWwuY29t',
                    ],
                    [
                        'href' => '/users/c2FiaW5lLmxhcm9jaGVAZ21haWwuY29t',
                    ],
                    [
                        'href' => '/users/Z2lsYmVydC5tYXJjZWxsZSBnaWxsZXNAZ21haWwuY29t',
                    ],
                ],
            ],
            'totalItems' => 10,
            'itemsPerPage' => 10,
            '_embedded' => [
                'item' => [
                    [
                        '_links' => [
                            'self' => [
                                'href' => '/users/70081b35-3de9-4112-9438-f87d0f12bdde',
                            ],
                        ],
                        'familyName' => 'Doe',
                        'givenName' => 'John',
                        'slug' => '70081b35-3de9-4112-9438-f87d0f12bdde',
                        'username' => 'john.doe@gmail.com',
                    ],
                    [
                        '_links' => [
                            'self' => [
                                'href' => '/users/d5f23798-9475-4fdf-8e6c-b0af4a3f5b71',
                            ],
                        ],
                        'familyName' => 'Doe',
                        'givenName' => 'Jane',
                        'slug' => 'd5f23798-9475-4fdf-8e6c-b0af4a3f5b71',
                        'username' => 'jane.doe@gmail.com',
                    ],
                    [
                        '_links' => [
                            'self' => [
                                'href' => '/users/YmVuamFtaW4uYmxpbkBnbWFpbC5jb20=',
                            ],
                        ],
                        'familyName' => 'Blin',
                        'givenName' => 'Benjamin',
                        'slug' => 'YmVuamFtaW4uYmxpbkBnbWFpbC5jb20=',
                        'username' => 'benjamin.blin@gmail.com',
                    ],
                    [
                        '_links' => [
                            'self' => [
                                'href' => '/users/bGF1cmUuZ29tZXNAZ21haWwuY29t',
                            ],
                        ],
                        'familyName' => 'Gomes',
                        'givenName' => 'Laure',
                        'slug' => 'bGF1cmUuZ29tZXNAZ21haWwuY29t',
                        'username' => 'laure.gomes@gmail.com',
                    ],
                    [
                        '_links' => [
                            'self' => [
                                'href' => '/users/dGhpZXJyeS5sZWJyZXRvbkBnbWFpbC5jb20=',
                            ],
                        ],
                        'familyName' => 'Lebreton',
                        'givenName' => 'Thierry',
                        'slug' => 'dGhpZXJyeS5sZWJyZXRvbkBnbWFpbC5jb20=',
                        'username' => 'thierry.lebreton@gmail.com',
                    ],
                    [
                        '_links' => [
                            'self' => [
                                'href' => '/users/bWFkZWxlaW5lLmhhbW9uQGdtYWlsLmNvbQ==',
                            ],
                        ],
                        'familyName' => 'Hamon',
                        'givenName' => 'Madeleine',
                        'slug' => 'bWFkZWxlaW5lLmhhbW9uQGdtYWlsLmNvbQ==',
                        'username' => 'madeleine.hamon@gmail.com',
                    ],
                    [
                        '_links' => [
                            'self' => [
                                'href' => '/users/cGF0cmljay5iZXJnZXJAZ21haWwuY29t',
                            ],
                        ],
                        'familyName' => 'Berger',
                        'givenName' => 'Patrick',
                        'slug' => 'cGF0cmljay5iZXJnZXJAZ21haWwuY29t',
                        'username' => 'patrick.berger@gmail.com',
                    ],
                    [
                        '_links' => [
                            'self' => [
                                'href' => '/users/c2FiaW5lLmxhcm9jaGVAZ21haWwuY29t',
                            ],
                        ],
                        'familyName' => 'Laroche',
                        'givenName' => 'Sabine',
                        'slug' => 'c2FiaW5lLmxhcm9jaGVAZ21haWwuY29t',
                        'username' => 'sabine.laroche@gmail.com',
                    ],
                    [
                        '_links' => [
                            'self' => [
                                'href' => '/users/Z2lsYmVydC5tYXJjZWxsZSBnaWxsZXNAZ21haWwuY29t',
                            ],
                        ],
                        'familyName' => 'Marcelle Gilles',
                        'givenName' => 'Gilbert',
                        'slug' => 'Z2lsYmVydC5tYXJjZWxsZSBnaWxsZXNAZ21haWwuY29t',
                        'username' => 'gilbert.marcelle gilles@gmail.com',
                    ],
                    [
                        '_links' => [
                            'self' => [
                                'href' => '/users/MDYyMjkyMDg1NQ==',
                            ],
                        ],
                        'familyName' => 'Gillet',
                        'givenName' => 'Julie',
                        'slug' => 'MDYyMjkyMDg1NQ==',
                        'username' => '0622920855',
                    ],
                ],
            ],
        ];

        $provider = new HalProvider($this->config);
        $this->assertTrue($provider->supportPagination($data, $this->response, $this->responseDefinition));
    }

    public function testShouldSupportPaginationWithoutEmbed()
    {
        $data = [
            '_links' => [
                'item' => [
                    [
                        'href' => '/users/70081b35-3de9-4112-9438-f87d0f12bdde',
                    ],
                    [
                        'href' => '/users/d5f23798-9475-4fdf-8e6c-b0af4a3f5b71',
                    ],
                    [
                        'href' => '/users/YmVuamFtaW4uYmxpbkBnbWFpbC5jb20=',
                    ],
                    [
                        'href' => '/users/bGF1cmUuZ29tZXNAZ21haWwuY29t',
                    ],
                    [
                        'href' => '/users/dGhpZXJyeS5sZWJyZXRvbkBnbWFpbC5jb20=',
                    ],
                    [
                        'href' => '/users/bWFkZWxlaW5lLmhhbW9uQGdtYWlsLmNvbQ==',
                    ],
                    [
                        'href' => '/users/cGF0cmljay5iZXJnZXJAZ21haWwuY29t',
                    ],
                    [
                        'href' => '/users/c2FiaW5lLmxhcm9jaGVAZ21haWwuY29t',
                    ],
                    [
                        'href' => '/users/Z2lsYmVydC5tYXJjZWxsZSBnaWxsZXNAZ21haWwuY29t',
                    ],
                ],
            ],
            'totalItems' => 10,
            'itemsPerPage' => 10,
            '_embedded' => [
                'item' => [
                    [
                        '_links' => [
                            'self' => [
                                'href' => '/users/d5f23798-9475-4fdf-8e6c-b0af4a3f5b71',
                            ],
                        ],
                        'familyName' => 'Doe',
                        'givenName' => 'Jane',
                        'slug' => 'd5f23798-9475-4fdf-8e6c-b0af4a3f5b71',
                        'username' => 'jane.doe@gmail.com',
                    ],
                    [
                        '_links' => [
                            'self' => [
                                'href' => '/users/YmVuamFtaW4uYmxpbkBnbWFpbC5jb20=',
                            ],
                        ],
                        'familyName' => 'Blin',
                        'givenName' => 'Benjamin',
                        'slug' => 'YmVuamFtaW4uYmxpbkBnbWFpbC5jb20=',
                        'username' => 'benjamin.blin@gmail.com',
                    ],
                    [
                        '_links' => [
                            'self' => [
                                'href' => '/users/70081b35-3de9-4112-9438-f87d0f12bdde',
                            ],
                        ],
                        'familyName' => 'Doe',
                        'givenName' => 'John',
                        'slug' => '70081b35-3de9-4112-9438-f87d0f12bdde',
                        'username' => 'john.doe@gmail.com',
                    ],
                    [
                        '_links' => [
                            'self' => [
                                'href' => '/users/bGF1cmUuZ29tZXNAZ21haWwuY29t',
                            ],
                        ],
                        'familyName' => 'Gomes',
                        'givenName' => 'Laure',
                        'slug' => 'bGF1cmUuZ29tZXNAZ21haWwuY29t',
                        'username' => 'laure.gomes@gmail.com',
                    ],
                    [
                        '_links' => [
                            'self' => [
                                'href' => '/users/dGhpZXJyeS5sZWJyZXRvbkBnbWFpbC5jb20=',
                            ],
                        ],
                        'familyName' => 'Lebreton',
                        'givenName' => 'Thierry',
                        'slug' => 'dGhpZXJyeS5sZWJyZXRvbkBnbWFpbC5jb20=',
                        'username' => 'thierry.lebreton@gmail.com',
                    ],
                    [
                        '_links' => [
                            'self' => [
                                'href' => '/users/bWFkZWxlaW5lLmhhbW9uQGdtYWlsLmNvbQ==',
                            ],
                        ],
                        'familyName' => 'Hamon',
                        'givenName' => 'Madeleine',
                        'slug' => 'bWFkZWxlaW5lLmhhbW9uQGdtYWlsLmNvbQ==',
                        'username' => 'madeleine.hamon@gmail.com',
                    ],
                    [
                        '_links' => [
                            'self' => [
                                'href' => '/users/cGF0cmljay5iZXJnZXJAZ21haWwuY29t',
                            ],
                        ],
                        'familyName' => 'Berger',
                        'givenName' => 'Patrick',
                        'slug' => 'cGF0cmljay5iZXJnZXJAZ21haWwuY29t',
                        'username' => 'patrick.berger@gmail.com',
                    ],
                    [
                        '_links' => [
                            'self' => [
                                'href' => '/users/c2FiaW5lLmxhcm9jaGVAZ21haWwuY29t',
                            ],
                        ],
                        'familyName' => 'Laroche',
                        'givenName' => 'Sabine',
                        'slug' => 'c2FiaW5lLmxhcm9jaGVAZ21haWwuY29t',
                        'username' => 'sabine.laroche@gmail.com',
                    ],
                    [
                        '_links' => [
                            'self' => [
                                'href' => '/users/Z2lsYmVydC5tYXJjZWxsZSBnaWxsZXNAZ21haWwuY29t',
                            ],
                        ],
                        'familyName' => 'Marcelle Gilles',
                        'givenName' => 'Gilbert',
                        'slug' => 'Z2lsYmVydC5tYXJjZWxsZSBnaWxsZXNAZ21haWwuY29t',
                        'username' => 'gilbert.marcelle gilles@gmail.com',
                    ],
                    [
                        '_links' => [
                            'self' => [
                                'href' => '/users/MDYyMjkyMDg1NQ==',
                            ],
                        ],
                        'familyName' => 'Gillet',
                        'givenName' => 'Julie',
                        'slug' => 'MDYyMjkyMDg1NQ==',
                        'username' => '0622920855',
                    ],
                ],
            ],
        ];

        $provider = new HalProvider($this->config);
        $this->assertFalse($provider->supportPagination($data, $this->response, $this->responseDefinition));
    }

    public function testShouldHavePaginationWithoutData()
    {
        $data = [
            'itemsPerPage' => 10,
            'totalItems' => 0,
            '_embedded' => [],
        ];

        $provider = new HalProvider($this->config);
        $pagination = $provider->getPagination($data, $this->response, $this->responseDefinition);

        $this->assertInstanceOf(Pagination::class, $pagination);
        $this->assertSame(1, $pagination->getPage());
        $this->assertSame(10, $pagination->getPerPage());
        $this->assertSame(0, $pagination->getTotalItems());
        $this->assertSame(0, $pagination->getTotalPages());

        $this->assertInstanceOf(PaginationLinks::class, $pagination->getLinks());

        $links = $pagination->getLinks();
        $this->assertSame('', $links->getFirst());
        $this->assertSame('', $links->getLast());
        $this->assertNull($links->getNext());
        $this->assertNull($links->getPrev());
    }

    /**
     * @dataProvider dataProviderItHavePagination
     */
    public function testShouldHavePagination(array $data, array $expected, array $keys)
    {
        $provider = new HalProvider($this->config);
        $pagination = $provider->getPagination($data, $this->response, $this->responseDefinition);

        $this->assertInstanceOf(Pagination::class, $pagination);
        $this->assertSame($expected['page'], $pagination->getPage());
        $this->assertSame($expected['perPage'], $pagination->getPerPage());
        $this->assertSame($expected['totalItems'], $pagination->getTotalItems());
        $this->assertSame($expected['totalPages'], $pagination->getTotalPages());

        $this->assertInstanceOf(PaginationLinks::class, $pagination->getLinks());

        $links = $pagination->getLinks();
        $this->assertSame($expected['first'], $links->getFirst());
        $this->assertSame($expected['last'], $links->getLast());
        $this->assertSame($expected['next'], $links->getNext());
        $this->assertSame($expected['prev'], $links->getPrev());

        $this->assertSame($keys, array_keys($data[0]));
    }

    public function dataProviderItHavePagination(): array
    {
        return [
            [
                json_decode(file_get_contents(realpath(sprintf('%s/../../fixtures/pagination/pagination_with_embed_self_page.json', __DIR__))), true),
                [
                    'page' => 1,
                    'perPage' => 2,
                    'totalItems' => 2,
                    'totalPages' => 1,
                    'first' => '/videos?foo=bar',
                    'last' => '/videos?foo=bar',
                    'next' => null,
                    'prev' => null,
                ],
                [
                    'category',
                    'nickName',
                    'slug',
                    'score',
                    'dateCreated',
                    'dateModified',
                ],
            ],
            [
                json_decode(file_get_contents(realpath(sprintf('%s/../../fixtures/pagination/pagination_with_embed_first_page.json', __DIR__))), true),
                [
                    'page' => 1,
                    'perPage' => 2,
                    'totalItems' => 4,
                    'totalPages' => 2,
                    'first' => '/videos?page=1',
                    'last' => '/videos?page=2',
                    'next' => '/videos?page=2',
                    'prev' => null,
                ],
                [
                    'category',
                    'nickName',
                    'slug',
                    'score',
                    'dateCreated',
                    'dateModified',
                ],
            ],
            [
                json_decode(file_get_contents(realpath(sprintf('%s/../../fixtures/pagination/pagination_with_embed_second_page.json', __DIR__))), true),
                [
                    'page' => 2,
                    'perPage' => 2,
                    'totalItems' => 4,
                    'totalPages' => 2,
                    'first' => '/videos?page=1',
                    'last' => '/videos?page=2',
                    'next' => null,
                    'prev' => '/videos?page=1',
                ],
                [
                    'category',
                    'nickName',
                    'slug',
                    'score',
                    'dateCreated',
                    'dateModified',
                ],
            ],
            [
                json_decode(file_get_contents(realpath(sprintf('%s/../../fixtures/pagination/pagination_with_embed_last_page.json', __DIR__))), true),
                [
                    'page' => 2,
                    'perPage' => 3,
                    'totalItems' => 10,
                    'totalPages' => 4,
                    'first' => '/videos?page=1',
                    'last' => '/videos?page=4',
                    'next' => '/videos?page=3',
                    'prev' => '/videos?page=1',
                ],
                [
                    'category',
                    'nickName',
                    'slug',
                    'score',
                    'dateCreated',
                    'dateModified',
                ],
            ],

            [
                json_decode(file_get_contents(realpath(sprintf('%s/../../fixtures/pagination/pagination_without_embed_first_page.json', __DIR__))), true),
                [
                    'page' => 1,
                    'perPage' => 10,
                    'totalItems' => 10,
                    'totalPages' => 1,
                    'first' => '/users?page=1',
                    'last' => '/users?page=1',
                    'next' => null,
                    'prev' => null,
                ],
                [
                    'familyName',
                    'givenName',
                    'slug',
                    'username',
                ],
            ],
        ];
    }

    public function testShouldHavePaginationWithEmbedAndPagination()
    {
        $data = json_decode(file_get_contents(realpath(sprintf('%s/../../fixtures/pagination/pagination_with_embed_collection.json', __DIR__))), true);

        $provider = new HalProvider($this->config);
        $pagination = $provider->getPagination($data, $this->response, $this->responseDefinition);

        $this->assertInstanceOf(Pagination::class, $pagination);
        $this->assertSame(1, $pagination->getPage());
        $this->assertSame(30, $pagination->getPerPage());
        $this->assertSame(269, $pagination->getTotalItems());
        $this->assertSame(9, $pagination->getTotalPages());

        $this->assertInstanceOf(PaginationLinks::class, $pagination->getLinks());

        $links = $pagination->getLinks();
        $this->assertSame('/drivers?foo=bar&page=1&bar=baz', $links->getFirst());
        $this->assertSame('/drivers?foo=bar&page=9&bar=baz', $links->getLast());
        $this->assertSame('/drivers?foo=bar&page=2&bar=baz', $links->getNext());
        $this->assertSame(null, $links->getPrev());

        $this->assertSame(
            [
                'company' => [
                    'name' => 'Renard Marin S.A.R.L.',
                    'clientRef' => 'aff97e9d-f87b-390b-b4cd-bafd58719ed0',
                    'taxId' => '783514803596521',
                    'kind' => 'independent',
                    'code' => null,
                    'dateCreated' => '2022-05-10T10:44:00+02:00',
                    'dateModified' => '2022-05-10T10:44:00+02:00',
                ],
                'vehicle' => [
                    'vehicleIdentificationNumber' => 'XX000XX',
                    'vehicleIdentificationNumberFormatted' => 'XX000XX',
                    'uuid' => 'WFgwMDBYWA==',
                    'fuelType' => 'hybrid',
                    'dateModelCreation' => '1998-05-18T00:00:00+02:00',
                    'modelGeneration' => 'lorem ipsum',
                    'plv' => true,
                    'warranty' => 'unknown',
                    'photo' => null,
                    'model' => [
                        'title' => 'A6',
                        'slug' => 'a6',
                        'dateModelRelease' => '2022-05-10T10:44:00+02:00',
                        'dateCreated' => '2022-05-10T10:44:00+02:00',
                        'dateModified' => '2022-05-10T10:44:00+02:00',
                        'brand' => [
                            'title' => 'Audi',
                            'slug' => 'audi',
                            'dateCreated' => '2022-05-10T10:43:59+02:00',
                            'dateModified' => '2022-05-10T10:43:59+02:00',
                        ],
                    ],
                    'color' => [
                        'title' => 'Black',
                        'slug' => 'black',
                        'dateCreated' => '2022-05-10T10:43:59+02:00',
                        'dateModified' => '2022-05-10T10:43:59+02:00',
                    ],
                ],
                'cities' => [
                    [
                        'city' => [
                            'title' => 'Toulouse',
                            'slug' => 'toulouse',
                        ],
                        'main' => true,
                    ],
                ],
                'givenName' => 'Julie',
                'familyName' => 'Gillet',
                'uuid' => 'MDYyMjkyMDg1NQ==',
                'phone' => '0622920855',
                'device' => null,
                'locale' => 'fr',
                'status' => [
                    'confirmed' => 1,
                    'unblocked' => 1,
                    'email_valid' => 1,
                    'password_valid' => 1,
                ],
                'vehicleType' => 'vtc',
                'lastLoginDate' => '2022-05-10T10:44:00+02:00',
                'dateCreated' => '2022-05-10T10:44:00+02:00',
                'dateModified' => '2022-05-10T10:44:00+02:00',
            ],
            $data[0]
        );
    }
}
