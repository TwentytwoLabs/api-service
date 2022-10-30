<?php

declare(strict_types=1);

namespace TwentytwoLabs\Api\Service\Tests;

use Assert\AssertionFailedException;
use GuzzleHttp\Psr7\Request;
use Http\Client\HttpAsyncClient;
use Http\Message\MessageFactory;
use Http\Message\UriFactory;
use Http\Promise\FulfilledPromise;
use Http\Promise\Promise;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;
use Psr\Log\LoggerInterface;
use Rize\UriTemplate;
use Symfony\Component\Serializer\SerializerInterface;
use TwentytwoLabs\Api\Definition\Parameter;
use TwentytwoLabs\Api\Definition\Parameters;
use TwentytwoLabs\Api\Definition\RequestDefinition;
use TwentytwoLabs\Api\Definition\ResponseDefinition;
use TwentytwoLabs\Api\Schema;
use TwentytwoLabs\Api\Service\ApiService;
use TwentytwoLabs\Api\Service\Exception\RequestViolations;
use TwentytwoLabs\Api\Service\Exception\ResponseViolations;
use TwentytwoLabs\Api\Service\Resource\Collection;
use TwentytwoLabs\Api\Service\Resource\ErrorInterface;
use TwentytwoLabs\Api\Service\Resource\Item;
use TwentytwoLabs\Api\Service\Resource\ResourceInterface;
use TwentytwoLabs\Api\Validator\MessageValidator;

/**
 * Class ApiServiceTest.
 */
class ApiServiceTest extends TestCase
{
    private Schema $schema;
    private UriFactory $uriFactory;
    private UriTemplate $uriTemplate;
    private $httpClient;
    private MessageFactory $messageFactory;
    private MessageValidator $messageValidator;
    private SerializerInterface $serializer;
    private LoggerInterface $logger;
    private array $config;

    protected function setUp(): void
    {
        $this->schema = $this->createMock(Schema::class);
        $this->uriFactory = $this->createMock(UriFactory::class);
        $this->uriTemplate = $this->createMock(UriTemplate::class);
        $this->httpClient = $this->createMock(ClientInterface::class);
        $this->messageFactory = $this->createMock(MessageFactory::class);
        $this->messageValidator = $this->createMock(MessageValidator::class);
        $this->serializer = $this->createMock(SerializerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->config = [];
    }

    /**
     * @dataProvider getCorruptConfigs
     */
    public function testShouldThrowExceptionBecauseConfigsSTypeIsDifferent(array $configs, string $message)
    {
        $this->expectException(AssertionFailedException::class);
        $this->expectExceptionMessage($message);

        $this->config = $configs;

        $this->getApiService();
    }

    public function getCorruptConfigs(): array
    {
        return [
            [
                ['returnResponse' => 0],
                'Value "0" is not a boolean.',
            ],
            [
                ['returnResponse' => 1],
                'Value "1" is not a boolean.',
            ],
            [
                ['returnResponse' => 'true'],
                'Value "true" is not a boolean.',
            ],
            [
                ['returnResponse' => 'false'],
                'Value "false" is not a boolean.',
            ],
            [
                ['validateRequest' => 0],
                'Value "0" is not a boolean.',
            ],
            [
                ['validateRequest' => 1],
                'Value "1" is not a boolean.',
            ],
            [
                ['validateRequest' => 'true'],
                'Value "true" is not a boolean.',
            ],
            [
                ['validateRequest' => 'false'],
                'Value "false" is not a boolean.',
            ],
            [
                ['validateResponse' => 0],
                'Value "0" is not a boolean.',
            ],
            [
                ['validateResponse' => 1],
                'Value "1" is not a boolean.',
            ],
            [
                ['validateResponse' => 'true'],
                'Value "true" is not a boolean.',
            ],
            [
                ['validateResponse' => 'false'],
                'Value "false" is not a boolean.',
            ],
            [
                ['baseUri' => 0],
                'Value "0" expected to be string, type integer given.',
            ],
        ];
    }

    public function testShouldNotBuildBaseUriBecauseSchemesIsEmpty()
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('You need to provide at least on scheme in your API Schema');

        $this->schema->expects($this->once())->method('getSchemes')->willReturn([]);
        $this->schema->expects($this->never())->method('getHost');

        $this->uriFactory->expects($this->never())->method('createUri');

        $this->getApiService();
    }

    public function testShouldNotBuildBaseUriBecauseSchemesIsNotForInternet()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot choose a proper scheme from the API Schema. Supported: https, http');

        $this->schema->expects($this->exactly(2))->method('getSchemes')->willReturn(['ftp']);
        $this->schema->expects($this->never())->method('getHost');

        $this->uriFactory->expects($this->never())->method('createUri');

        $this->getApiService();
    }

    public function testShouldNotBuildBaseUriBecauseHostIsNotDefined()
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('The host in the API Schema should not be null');

        $this->schema->expects($this->exactly(2))->method('getSchemes')->willReturn(['http']);
        $this->schema->expects($this->once())->method('getHost')->willReturn('');

        $this->uriFactory->expects($this->never())->method('createUri');

        $this->getApiService();
    }

    public function testShouldBuildBaseUriWhenItIsNotDefinedInConfigsWithHttp()
    {
        $uri = $this->createMock(UriInterface::class);

        $this->schema->expects($this->exactly(2))->method('getSchemes')->willReturn(['http']);
        $this->schema->expects($this->once())->method('getHost')->willReturn('example.org');

        $this->uriFactory->expects($this->once())->method('createUri')->with('http://example.org')->willReturn($uri);

        $service = $this->getApiService();
        $this->assertSame($this->schema, $service->getSchema());
    }

    public function testShouldBuildBaseUriWhenItIsNotDefinedInConfigsWithHttps()
    {
        $uri = $this->createMock(UriInterface::class);

        $this->schema->expects($this->exactly(2))->method('getSchemes')->willReturn(['http', 'https']);
        $this->schema->expects($this->once())->method('getHost')->willReturn('example.org');

        $this->uriFactory->expects($this->once())->method('createUri')->with('https://example.org')->willReturn($uri);

        $service = $this->getApiService();
        $this->assertSame($this->schema, $service->getSchema());
    }

    public function testShouldBuildBaseUriWhenItIsDefinedInConfigs()
    {
        $this->config['baseUri'] = 'https://example.org';

        $uri = $this->createMock(UriInterface::class);

        $this->schema->expects($this->never())->method('getSchemes');
        $this->schema->expects($this->never())->method('getHost');

        $this->uriFactory->expects($this->once())->method('createUri')->with('https://example.org')->willReturn($uri);

        $service = $this->getApiService();
        $this->assertSame($this->schema, $service->getSchema());
    }

    public function testShouldCallApiInPostWithOutErrorButWithOutCheckRequestAndWithOutCheckResponse()
    {
        $requestParameter = $this->createMock(Parameter::class);
        $requestParameter->expects($this->once())->method('getLocation')->willReturn('body');

        $requestParameters = $this->createMock(Parameters::class);
        $requestParameters->expects($this->once())->method('getByName')->with('user')->willReturn($requestParameter);

        $responseDefinition = $this->createMock(ResponseDefinition::class);
        $responseDefinition->expects($this->once())->method('hasBodySchema')->willReturn(true);

        $requestDefinition = $this->createMock(RequestDefinition::class);
        $requestDefinition->expects($this->never())->method('getOperationId');
        $requestDefinition
            ->expects($this->once())
            ->method('getContentTypes')
            ->willReturn(['application/json'])
        ;
        $requestDefinition->expects($this->once())->method('getRequestParameters')->willReturn($requestParameters);
        $requestDefinition->expects($this->exactly(2))->method('getMethod')->willReturn('POST');
        $requestDefinition->expects($this->once())->method('getAccepts')->willReturn(['application/json']);
        $requestDefinition->expects($this->once())->method('getPathTemplate')->willReturn('/users');
        $requestDefinition->expects($this->once())->method('getResponseDefinition')->with(201)->willReturn($responseDefinition);

        $uri = $this->createMock(UriInterface::class);
        $uri->expects($this->once())->method('withPath')->with('/users')->willReturnSelf();
        $uri->expects($this->once())->method('withQuery')->with('')->willReturnSelf();

        $this->uriFactory->expects($this->once())->method('createUri')->with('https://example.org')->willReturn($uri);

        $request = $this->createMock(Request::class);

        $this->messageFactory
            ->expects($this->once())
            ->method('createRequest')
            ->with(
                'POST',
                $uri,
                ['Content-Type' => 'application/json', 'Accept' => 'application/json'],
                '{"username":"bar@exemple.org"}'
            )
            ->willReturn($request)
        ;

        $body = $this->createMock(StreamInterface::class);
        $body->expects($this->once())->method('__toString')->willReturn('{"username":"bar@exemple.org"}');

        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->exactly(2))->method('getStatusCode')->willReturn(201);
        $response->expects($this->exactly(2))->method('getHeaderLine')->with('Content-Type')->willReturn('application/json');
        $response->expects($this->once())->method('getBody')->willReturn($body);

        $this->httpClient->expects($this->once())->method('sendRequest')->with($request)->willReturn($response);

        $this->messageValidator->expects($this->never())->method('validateRequest');
        $this->messageValidator->expects($this->never())->method('hasViolations');
        $this->messageValidator->expects($this->never())->method('getViolations');
        $this->messageValidator->expects($this->never())->method('validateResponse');

        $collection = $this->createMock(Collection::class);

        $this->serializer
            ->expects($this->once())
            ->method('serialize')
            ->with(['username' => 'bar@exemple.org'], 'json')
            ->willReturn('{"username":"bar@exemple.org"}')
        ;
        $this->serializer
            ->expects($this->once())
            ->method('deserialize')
            ->with(
                '{"username":"bar@exemple.org"}',
                ResourceInterface::class,
                'json',
                ['response' => $response, 'responseDefinition' => $responseDefinition, 'request' => $request]
            )
            ->willReturn($collection)
        ;

        $this->schema
            ->expects($this->once())
            ->method('getRequestDefinition')
            ->with('postUserCollection')
            ->willReturn($requestDefinition)
        ;
        $this->schema->expects($this->never())->method('getSchemes');
        $this->schema->expects($this->never())->method('getHost');

        $this->uriTemplate->expects($this->once())->method('expand')->with('/users', [])->willReturn('/users');

        $this->config['baseUri'] = 'https://example.org';
        $this->config['validateRequest'] = false;

        $service = $this->getApiService();
        $this->assertSame(
            $collection,
            $service->call(
                'postUserCollection',
                ['user' => ['username' => 'bar@exemple.org']]
            )
        );
        $this->assertSame($this->schema, $service->getSchema());
    }

    public function testShouldCallApiInPostWithOutError()
    {
        $requestParameter = $this->createMock(Parameter::class);
        $requestParameter->expects($this->once())->method('getLocation')->willReturn('body');

        $requestParameters = $this->createMock(Parameters::class);
        $requestParameters->expects($this->once())->method('getByName')->with('user')->willReturn($requestParameter);

        $responseDefinition = $this->createMock(ResponseDefinition::class);
        $responseDefinition->expects($this->once())->method('hasBodySchema')->willReturn(true);

        $requestDefinition = $this->createMock(RequestDefinition::class);
        $requestDefinition->expects($this->never())->method('getOperationId');
        $requestDefinition
            ->expects($this->once())
            ->method('getContentTypes')
            ->willReturn(['application/json'])
        ;
        $requestDefinition->expects($this->once())->method('getRequestParameters')->willReturn($requestParameters);
        $requestDefinition->expects($this->exactly(2))->method('getMethod')->willReturn('POST');
        $requestDefinition->expects($this->once())->method('getAccepts')->willReturn(['application/json']);
        $requestDefinition->expects($this->once())->method('getPathTemplate')->willReturn('/users');
        $requestDefinition->expects($this->once())->method('getResponseDefinition')->with(201)->willReturn($responseDefinition);

        $uri = $this->createMock(UriInterface::class);
        $uri->expects($this->once())->method('withPath')->with('/users')->willReturnSelf();
        $uri->expects($this->once())->method('withQuery')->with('')->willReturnSelf();

        $this->uriFactory->expects($this->once())->method('createUri')->with('https://example.org')->willReturn($uri);

        $request = $this->createMock(Request::class);

        $this->messageFactory
            ->expects($this->once())
            ->method('createRequest')
            ->with(
                'POST',
                $uri,
                ['Content-Type' => 'application/json', 'Accept' => 'application/json'],
                '{"username":"bar@exemple.org"}'
            )
            ->willReturn($request)
        ;

        $body = $this->createMock(StreamInterface::class);
        $body->expects($this->once())->method('__toString')->willReturn('{"username":"bar@exemple.org"}');

        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->exactly(2))->method('getStatusCode')->willReturn(201);
        $response->expects($this->exactly(2))->method('getHeaderLine')->with('Content-Type')->willReturn('application/json');
        $response->expects($this->once())->method('getBody')->willReturn($body);

        $this->httpClient->expects($this->once())->method('sendRequest')->with($request)->willReturn($response);

        $this->messageValidator->expects($this->once())->method('validateRequest')->with($request, $requestDefinition);
        $this->messageValidator->expects($this->exactly(2))->method('hasViolations')->willReturn(false);
        $this->messageValidator->expects($this->never())->method('getViolations');
        $this->messageValidator->expects($this->once())->method('validateResponse')->with($response, $requestDefinition);

        $collection = $this->createMock(Collection::class);

        $this->serializer
            ->expects($this->once())
            ->method('serialize')
            ->with(['username' => 'bar@exemple.org'], 'json')
            ->willReturn('{"username":"bar@exemple.org"}')
        ;
        $this->serializer
            ->expects($this->once())
            ->method('deserialize')
            ->with(
                '{"username":"bar@exemple.org"}',
                ResourceInterface::class,
                'json',
                ['response' => $response, 'responseDefinition' => $responseDefinition, 'request' => $request]
            )
            ->willReturn($collection)
        ;

        $this->schema
            ->expects($this->once())
            ->method('getRequestDefinition')
            ->with('postUserCollection')
            ->willReturn($requestDefinition)
        ;
        $this->schema->expects($this->never())->method('getSchemes');
        $this->schema->expects($this->never())->method('getHost');

        $this->uriTemplate->expects($this->once())->method('expand')->with('/users', [])->willReturn('/users');

        $this->config['baseUri'] = 'https://example.org';
        $this->config['validateResponse'] = true;

        $service = $this->getApiService();
        $this->assertSame(
            $collection,
            $service->call(
                'postUserCollection',
                ['user' => ['username' => 'bar@exemple.org']]
            )
        );
        $this->assertSame($this->schema, $service->getSchema());
    }

    public function testShouldCallApiInPostWithErrors()
    {
        $requestParameter = $this->createMock(Parameter::class);
        $requestParameter->expects($this->once())->method('getLocation')->willReturn('body');

        $requestParameters = $this->createMock(Parameters::class);
        $requestParameters->expects($this->once())->method('getByName')->with('user')->willReturn($requestParameter);

        $responseDefinition = $this->createMock(ResponseDefinition::class);
        $responseDefinition->expects($this->once())->method('hasBodySchema')->willReturn(true);

        $requestDefinition = $this->createMock(RequestDefinition::class);
        $requestDefinition->expects($this->never())->method('getOperationId');
        $requestDefinition
            ->expects($this->once())
            ->method('getContentTypes')
            ->willReturn(['application/json'])
        ;
        $requestDefinition->expects($this->once())->method('getRequestParameters')->willReturn($requestParameters);
        $requestDefinition->expects($this->exactly(2))->method('getMethod')->willReturn('POST');
        $requestDefinition->expects($this->once())->method('getAccepts')->willReturn(['application/json']);
        $requestDefinition->expects($this->once())->method('getPathTemplate')->willReturn('/users');
        $requestDefinition->expects($this->once())->method('getResponseDefinition')->with(500)->willReturn($responseDefinition);

        $uri = $this->createMock(UriInterface::class);
        $uri->expects($this->once())->method('withPath')->with('/users')->willReturnSelf();
        $uri->expects($this->once())->method('withQuery')->with('')->willReturnSelf();

        $this->uriFactory->expects($this->once())->method('createUri')->with('https://example.org')->willReturn($uri);

        $request = $this->createMock(Request::class);

        $this->messageFactory
            ->expects($this->once())
            ->method('createRequest')
            ->with(
                'POST',
                $uri,
                ['Content-Type' => 'application/json', 'Accept' => 'application/json'],
                '{"username":"bar@exemple.org"}'
            )
            ->willReturn($request)
        ;

        $body = $this->createMock(StreamInterface::class);
        $body->expects($this->once())->method('__toString')->willReturn('{"code":500,"message":"Internal Server Error"}');

        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->exactly(2))->method('getStatusCode')->willReturn(500);
        $response->expects($this->exactly(2))->method('getHeaderLine')->with('Content-Type')->willReturn('application/json');
        $response->expects($this->once())->method('getBody')->willReturn($body);

        $this->httpClient->expects($this->once())->method('sendRequest')->with($request)->willReturn($response);

        $this->messageValidator->expects($this->once())->method('validateRequest')->with($request, $requestDefinition);
        $this->messageValidator->expects($this->exactly(2))->method('hasViolations')->willReturn(false);
        $this->messageValidator->expects($this->never())->method('getViolations');
        $this->messageValidator->expects($this->once())->method('validateResponse')->with($response, $requestDefinition);

        $error = $this->createMock(ErrorInterface::class);

        $this->serializer
            ->expects($this->once())
            ->method('serialize')
            ->with(['username' => 'bar@exemple.org'], 'json')
            ->willReturn('{"username":"bar@exemple.org"}')
        ;
        $this->serializer
            ->expects($this->once())
            ->method('deserialize')
            ->with(
                '{"code":500,"message":"Internal Server Error"}',
                ErrorInterface::class,
                'json',
                ['response' => $response, 'responseDefinition' => $responseDefinition, 'request' => $request]
            )
            ->willReturn($error)
        ;

        $this->schema
            ->expects($this->once())
            ->method('getRequestDefinition')
            ->with('postUserCollection')
            ->willReturn($requestDefinition)
        ;
        $this->schema->expects($this->never())->method('getSchemes');
        $this->schema->expects($this->never())->method('getHost');

        $this->uriTemplate->expects($this->once())->method('expand')->with('/users', [])->willReturn('/users');

        $this->config['baseUri'] = 'https://example.org';
        $this->config['validateResponse'] = true;

        $service = $this->getApiService();
        $this->assertSame(
            $error,
            $service->call(
                'postUserCollection',
                ['user' => ['username' => 'bar@exemple.org']]
            )
        );
        $this->assertSame($this->schema, $service->getSchema());
    }

    public function testShouldCallApiInPostFormDataWithOutError()
    {
        $requestParameter = $this->createMock(Parameter::class);
        $requestParameter->expects($this->once())->method('getLocation')->willReturn('formData');

        $requestParameterPassword = $this->createMock(Parameter::class);
        $requestParameterPassword->expects($this->once())->method('getLocation')->willReturn('formData');

        $requestParameterFoo = $this->createMock(Parameter::class);
        $requestParameterFoo->expects($this->once())->method('getLocation')->willReturn('header');

        $requestParameters = $this->createMock(Parameters::class);
        $requestParameters
            ->expects($this->exactly(3))
            ->method('getByName')
            ->withConsecutive(['x-foo'], ['username'], ['password'])
            ->willReturnOnConsecutiveCalls($requestParameterFoo, $requestParameter, $requestParameterPassword)
        ;

        $responseDefinition = $this->createMock(ResponseDefinition::class);
        $responseDefinition->expects($this->once())->method('hasBodySchema')->willReturn(true);

        $requestDefinition = $this->createMock(RequestDefinition::class);
        $requestDefinition->expects($this->never())->method('getOperationId');
        $requestDefinition
            ->expects($this->once())
            ->method('getContentTypes')
            ->willReturn(['multipart/form-data'])
        ;
        $requestDefinition->expects($this->once())->method('getRequestParameters')->willReturn($requestParameters);
        $requestDefinition->expects($this->exactly(2))->method('getMethod')->willReturn('POST');
        $requestDefinition->expects($this->once())->method('getAccepts')->willReturn(['application/json']);
        $requestDefinition->expects($this->once())->method('getPathTemplate')->willReturn('/login_check');
        $requestDefinition->expects($this->once())->method('getResponseDefinition')->with(200)->willReturn($responseDefinition);

        $uri = $this->createMock(UriInterface::class);
        $uri->expects($this->once())->method('withPath')->with('/login_check')->willReturnSelf();
        $uri->expects($this->once())->method('withQuery')->with('')->willReturnSelf();

        $this->uriFactory->expects($this->once())->method('createUri')->with('https://example.org')->willReturn($uri);

        $request = $this->createMock(Request::class);

        $this->messageFactory
            ->expects($this->once())
            ->method('createRequest')
            ->with(
                'POST',
                $uri,
                [
                    'Content-Type' => 'multipart/form-data',
                    'Accept' => 'application/json',
                    'x-foo' => '73aca150-74a8-4d0e-95b2-e5e452c2bde1',
                ],
                'username=bar@exemple.org&password=1234'
            )
            ->willReturn($request)
        ;

        $body = $this->createMock(StreamInterface::class);
        $body->expects($this->once())->method('__toString')->willReturn('{"token":"6da3934e-e836-483a-a46f-88cd5288b03e"}');

        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->exactly(2))->method('getStatusCode')->willReturn(200);
        $response->expects($this->exactly(2))->method('getHeaderLine')->with('Content-Type')->willReturn('application/json');
        $response->expects($this->once())->method('getBody')->willReturn($body);

        $this->httpClient->expects($this->once())->method('sendRequest')->with($request)->willReturn($response);

        $this->messageValidator->expects($this->once())->method('validateRequest')->with($request, $requestDefinition);
        $this->messageValidator->expects($this->exactly(2))->method('hasViolations')->willReturn(false);
        $this->messageValidator->expects($this->never())->method('getViolations');
        $this->messageValidator->expects($this->once())->method('validateResponse')->with($response, $requestDefinition);

        $collection = $this->createMock(Collection::class);

        $this->serializer->expects($this->never())->method('serialize');
        $this->serializer
            ->expects($this->once())
            ->method('deserialize')
            ->with(
                '{"token":"6da3934e-e836-483a-a46f-88cd5288b03e"}',
                ResourceInterface::class,
                'json',
                ['response' => $response, 'responseDefinition' => $responseDefinition, 'request' => $request]
            )
            ->willReturn($collection)
        ;

        $this->schema
            ->expects($this->once())
            ->method('getRequestDefinition')
            ->with('postTokenCollection')
            ->willReturn($requestDefinition)
        ;
        $this->schema->expects($this->never())->method('getSchemes');
        $this->schema->expects($this->never())->method('getHost');

        $this->uriTemplate->expects($this->once())->method('expand')->with('/login_check', [])->willReturn('/login_check');

        $this->config['baseUri'] = 'https://example.org';
        $this->config['validateResponse'] = true;

        $service = $this->getApiService();
        $this->assertSame(
            $collection,
            $service->call(
                'postTokenCollection',
                [
                    'x-foo' => '73aca150-74a8-4d0e-95b2-e5e452c2bde1',
                    'username' => 'bar@exemple.org',
                    'password' => '1234',
                ]
            )
        );
        $this->assertSame($this->schema, $service->getSchema());
    }

    public function testShouldCallApiInPostFormDataWithError()
    {
        $requestParameter = $this->createMock(Parameter::class);
        $requestParameter->expects($this->once())->method('getLocation')->willReturn('formData');

        $requestParameterPassword = $this->createMock(Parameter::class);
        $requestParameterPassword->expects($this->once())->method('getLocation')->willReturn('formData');

        $requestParameters = $this->createMock(Parameters::class);
        $requestParameters
            ->expects($this->exactly(2))
            ->method('getByName')
            ->withConsecutive(['username'], ['password'])
            ->willReturnOnConsecutiveCalls($requestParameter, $requestParameterPassword)
        ;

        $responseDefinition = $this->createMock(ResponseDefinition::class);
        $responseDefinition->expects($this->once())->method('hasBodySchema')->willReturn(true);

        $requestDefinition = $this->createMock(RequestDefinition::class);
        $requestDefinition->expects($this->never())->method('getOperationId');
        $requestDefinition
            ->expects($this->once())
            ->method('getContentTypes')
            ->willReturn(['multipart/form-data'])
        ;
        $requestDefinition->expects($this->once())->method('getRequestParameters')->willReturn($requestParameters);
        $requestDefinition->expects($this->exactly(2))->method('getMethod')->willReturn('POST');
        $requestDefinition->expects($this->once())->method('getAccepts')->willReturn(['application/json']);
        $requestDefinition->expects($this->once())->method('getPathTemplate')->willReturn('/login_check');
        $requestDefinition->expects($this->once())->method('getResponseDefinition')->with(500)->willReturn($responseDefinition);

        $uri = $this->createMock(UriInterface::class);
        $uri->expects($this->once())->method('withPath')->with('/login_check')->willReturnSelf();
        $uri->expects($this->once())->method('withQuery')->with('')->willReturnSelf();

        $this->uriFactory->expects($this->once())->method('createUri')->with('https://example.org')->willReturn($uri);

        $request = $this->createMock(Request::class);

        $this->messageFactory
            ->expects($this->once())
            ->method('createRequest')
            ->with(
                'POST',
                $uri,
                ['Content-Type' => 'multipart/form-data', 'Accept' => 'application/json'],
                'username=bar@exemple.org&password=1234'
            )
            ->willReturn($request)
        ;

        $body = $this->createMock(StreamInterface::class);
        $body->expects($this->once())->method('__toString')->willReturn('{"token":"6da3934e-e836-483a-a46f-88cd5288b03e"}');

        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->exactly(2))->method('getStatusCode')->willReturn(500);
        $response->expects($this->exactly(2))->method('getHeaderLine')->with('Content-Type')->willReturn('application/json');
        $response->expects($this->once())->method('getBody')->willReturn($body);

        $this->httpClient->expects($this->once())->method('sendRequest')->with($request)->willReturn($response);

        $this->messageValidator->expects($this->once())->method('validateRequest')->with($request, $requestDefinition);
        $this->messageValidator->expects($this->exactly(2))->method('hasViolations')->willReturn(false);
        $this->messageValidator->expects($this->never())->method('getViolations');
        $this->messageValidator->expects($this->once())->method('validateResponse')->with($response, $requestDefinition);

        $error = $this->createMock(ErrorInterface::class);

        $this->serializer->expects($this->never())->method('serialize');
        $this->serializer
            ->expects($this->once())
            ->method('deserialize')
            ->with(
                '{"token":"6da3934e-e836-483a-a46f-88cd5288b03e"}',
                ErrorInterface::class,
                'json',
                ['response' => $response, 'responseDefinition' => $responseDefinition, 'request' => $request]
            )
            ->willReturn($error)
        ;

        $this->schema
            ->expects($this->once())
            ->method('getRequestDefinition')
            ->with('postTokenCollection')
            ->willReturn($requestDefinition)
        ;
        $this->schema->expects($this->never())->method('getSchemes');
        $this->schema->expects($this->never())->method('getHost');

        $this->uriTemplate->expects($this->once())->method('expand')->with('/login_check', [])->willReturn('/login_check');

        $this->config['baseUri'] = 'https://example.org';
        $this->config['validateResponse'] = true;

        $service = $this->getApiService();
        $this->assertSame(
            $error,
            $service->call(
                'postTokenCollection',
                ['username' => 'bar@exemple.org', 'password' => '1234']
            )
        );
        $this->assertSame($this->schema, $service->getSchema());
    }

    public function testShouldCallApiInGetForAResourceWithOutErrorButWithOutCheckRequestAndWithOutCheckResponse()
    {
        $requestParameter = $this->createMock(Parameter::class);
        $requestParameter->expects($this->once())->method('getLocation')->willReturn('path');

        $requestParameters = $this->createMock(Parameters::class);
        $requestParameters->expects($this->once())->method('getByName')->with('identifier')->willReturn($requestParameter);

        $responseDefinition = $this->createMock(ResponseDefinition::class);
        $responseDefinition->expects($this->once())->method('hasBodySchema')->willReturn(true);

        $requestDefinition = $this->createMock(RequestDefinition::class);
        $requestDefinition->expects($this->never())->method('getOperationId');
        $requestDefinition
            ->expects($this->once())
            ->method('getContentTypes')
            ->willReturn(['application/json'])
        ;
        $requestDefinition->expects($this->once())->method('getRequestParameters')->willReturn($requestParameters);
        $requestDefinition->expects($this->exactly(2))->method('getMethod')->willReturn('GET');
        $requestDefinition->expects($this->once())->method('getAccepts')->willReturn(['application/json']);
        $requestDefinition->expects($this->once())->method('getPathTemplate')->willReturn('/users/{identifier}');
        $requestDefinition->expects($this->once())->method('getResponseDefinition')->with(200)->willReturn($responseDefinition);

        $uri = $this->createMock(UriInterface::class);
        $uri->expects($this->once())->method('withPath')->with('/users/48200696-abbf-4787-9615-d8b7955eaee9')->willReturnSelf();
        $uri->expects($this->once())->method('withQuery')->with('')->willReturnSelf();

        $this->uriFactory->expects($this->once())->method('createUri')->with('https://example.org')->willReturn($uri);

        $request = $this->createMock(Request::class);

        $this->messageFactory
            ->expects($this->once())
            ->method('createRequest')
            ->with(
                'GET',
                $uri,
                ['Content-Type' => 'application/json', 'Accept' => 'application/json'],
                null
            )
            ->willReturn($request)
        ;

        $body = $this->createMock(StreamInterface::class);
        $body->expects($this->once())->method('__toString')->willReturn('{}');

        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->exactly(2))->method('getStatusCode')->willReturn(200);
        $response->expects($this->exactly(2))->method('getHeaderLine')->with('Content-Type')->willReturn('application/json');
        $response->expects($this->once())->method('getBody')->willReturn($body);

        $this->httpClient->expects($this->once())->method('sendRequest')->with($request)->willReturn($response);

        $this->messageValidator->expects($this->never())->method('validateRequest');
        $this->messageValidator->expects($this->never())->method('hasViolations');
        $this->messageValidator->expects($this->never())->method('getViolations');
        $this->messageValidator->expects($this->never())->method('validateResponse');

        $item = $this->createMock(Item::class);

        $this->serializer->expects($this->never())->method('serialize');
        $this->serializer
            ->expects($this->once())
            ->method('deserialize')
            ->with(
                '{}',
                ResourceInterface::class,
                'json',
                ['response' => $response, 'responseDefinition' => $responseDefinition, 'request' => $request]
            )
            ->willReturn($item)
        ;

        $this->schema
            ->expects($this->once())
            ->method('getRequestDefinition')
            ->with('getUserItem')
            ->willReturn($requestDefinition)
        ;
        $this->schema->expects($this->never())->method('getSchemes');
        $this->schema->expects($this->never())->method('getHost');

        $this->uriTemplate
            ->expects($this->once())
            ->method('expand')
            ->with('/users/{identifier}', ['identifier' => '48200696-abbf-4787-9615-d8b7955eaee9'])
            ->willReturn('/users/48200696-abbf-4787-9615-d8b7955eaee9')
        ;

        $this->config['baseUri'] = 'https://example.org';
        $this->config['validateRequest'] = false;

        $service = $this->getApiService();
        $this->assertSame($item, $service->call('getUserItem', ['identifier' => '48200696-abbf-4787-9615-d8b7955eaee9']));
        $this->assertSame($this->schema, $service->getSchema());
    }

    public function testShouldCallApiInGetForAResourceWithOutError()
    {
        $requestParameter = $this->createMock(Parameter::class);
        $requestParameter->expects($this->once())->method('getLocation')->willReturn('path');

        $requestParameters = $this->createMock(Parameters::class);
        $requestParameters->expects($this->once())->method('getByName')->with('identifier')->willReturn($requestParameter);

        $responseDefinition = $this->createMock(ResponseDefinition::class);
        $responseDefinition->expects($this->once())->method('hasBodySchema')->willReturn(true);

        $requestDefinition = $this->createMock(RequestDefinition::class);
        $requestDefinition->expects($this->never())->method('getOperationId');
        $requestDefinition
            ->expects($this->once())
            ->method('getContentTypes')
            ->willReturn(['application/json'])
        ;
        $requestDefinition->expects($this->once())->method('getRequestParameters')->willReturn($requestParameters);
        $requestDefinition->expects($this->exactly(2))->method('getMethod')->willReturn('GET');
        $requestDefinition->expects($this->once())->method('getAccepts')->willReturn(['application/json']);
        $requestDefinition->expects($this->once())->method('getPathTemplate')->willReturn('/users/{identifier}');
        $requestDefinition->expects($this->once())->method('getResponseDefinition')->with(200)->willReturn($responseDefinition);

        $uri = $this->createMock(UriInterface::class);
        $uri->expects($this->once())->method('withPath')->with('/users/48200696-abbf-4787-9615-d8b7955eaee9')->willReturnSelf();
        $uri->expects($this->once())->method('withQuery')->with('')->willReturnSelf();

        $this->uriFactory->expects($this->once())->method('createUri')->with('https://example.org')->willReturn($uri);

        $request = $this->createMock(Request::class);

        $this->messageFactory
            ->expects($this->once())
            ->method('createRequest')
            ->with(
                'GET',
                $uri,
                ['Content-Type' => 'application/json', 'Accept' => 'application/json'],
                null
            )
            ->willReturn($request)
        ;

        $body = $this->createMock(StreamInterface::class);
        $body->expects($this->once())->method('__toString')->willReturn('{}');

        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->exactly(2))->method('getStatusCode')->willReturn(200);
        $response->expects($this->exactly(2))->method('getHeaderLine')->with('Content-Type')->willReturn('application/json');
        $response->expects($this->once())->method('getBody')->willReturn($body);

        $this->httpClient->expects($this->once())->method('sendRequest')->with($request)->willReturn($response);

        $this->messageValidator->expects($this->once())->method('validateRequest')->with($request, $requestDefinition);
        $this->messageValidator->expects($this->exactly(2))->method('hasViolations')->willReturn(false);
        $this->messageValidator->expects($this->never())->method('getViolations');
        $this->messageValidator->expects($this->once())->method('validateResponse')->with($response, $requestDefinition);

        $item = $this->createMock(Item::class);

        $this->serializer->expects($this->never())->method('serialize');
        $this->serializer
            ->expects($this->once())
            ->method('deserialize')
            ->with(
                '{}',
                ResourceInterface::class,
                'json',
                ['response' => $response, 'responseDefinition' => $responseDefinition, 'request' => $request]
            )
            ->willReturn($item)
        ;

        $this->schema
            ->expects($this->once())
            ->method('getRequestDefinition')
            ->with('getUserItem')
            ->willReturn($requestDefinition)
        ;
        $this->schema->expects($this->never())->method('getSchemes');
        $this->schema->expects($this->never())->method('getHost');

        $this->uriTemplate
            ->expects($this->once())
            ->method('expand')
            ->with('/users/{identifier}', ['identifier' => '48200696-abbf-4787-9615-d8b7955eaee9'])
            ->willReturn('/users/48200696-abbf-4787-9615-d8b7955eaee9')
        ;

        $this->config['baseUri'] = 'https://example.org';
        $this->config['validateResponse'] = true;

        $service = $this->getApiService();
        $this->assertSame($item, $service->call('getUserItem', ['identifier' => '48200696-abbf-4787-9615-d8b7955eaee9']));
        $this->assertSame($this->schema, $service->getSchema());
    }

    public function testShouldCallApiInGetForAResourceWithErrors()
    {
        $requestParameter = $this->createMock(Parameter::class);
        $requestParameter->expects($this->once())->method('getLocation')->willReturn('path');

        $requestParameters = $this->createMock(Parameters::class);
        $requestParameters->expects($this->once())->method('getByName')->with('identifier')->willReturn($requestParameter);

        $responseDefinition = $this->createMock(ResponseDefinition::class);
        $responseDefinition->expects($this->once())->method('hasBodySchema')->willReturn(true);

        $requestDefinition = $this->createMock(RequestDefinition::class);
        $requestDefinition->expects($this->never())->method('getOperationId');
        $requestDefinition
            ->expects($this->once())
            ->method('getContentTypes')
            ->willReturn(['application/json'])
        ;
        $requestDefinition->expects($this->once())->method('getRequestParameters')->willReturn($requestParameters);
        $requestDefinition->expects($this->exactly(2))->method('getMethod')->willReturn('GET');
        $requestDefinition->expects($this->once())->method('getAccepts')->willReturn(['application/json']);
        $requestDefinition->expects($this->once())->method('getPathTemplate')->willReturn('/users/{identifier}');
        $requestDefinition->expects($this->once())->method('getResponseDefinition')->with(500)->willReturn($responseDefinition);

        $uri = $this->createMock(UriInterface::class);
        $uri->expects($this->once())->method('withPath')->with('/users/48200696-abbf-4787-9615-d8b7955eaee9')->willReturnSelf();
        $uri->expects($this->once())->method('withQuery')->with('')->willReturnSelf();

        $this->uriFactory->expects($this->once())->method('createUri')->with('https://example.org')->willReturn($uri);

        $request = $this->createMock(Request::class);

        $this->messageFactory
            ->expects($this->once())
            ->method('createRequest')
            ->with(
                'GET',
                $uri,
                ['Content-Type' => 'application/json', 'Accept' => 'application/json'],
                null
            )
            ->willReturn($request)
        ;

        $body = $this->createMock(StreamInterface::class);
        $body->expects($this->once())->method('__toString')->willReturn('{"code":500,"message":"Internal Serveur Error"}');

        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->exactly(2))->method('getStatusCode')->willReturn(500);
        $response->expects($this->exactly(2))->method('getHeaderLine')->with('Content-Type')->willReturn('application/json');
        $response->expects($this->once())->method('getBody')->willReturn($body);

        $this->httpClient->expects($this->once())->method('sendRequest')->with($request)->willReturn($response);

        $this->messageValidator->expects($this->once())->method('validateRequest')->with($request, $requestDefinition);
        $this->messageValidator->expects($this->exactly(2))->method('hasViolations')->willReturn(false);
        $this->messageValidator->expects($this->never())->method('getViolations');
        $this->messageValidator->expects($this->once())->method('validateResponse')->with($response, $requestDefinition);

        $error = $this->createMock(ErrorInterface::class);

        $this->serializer->expects($this->never())->method('serialize');
        $this->serializer
            ->expects($this->once())
            ->method('deserialize')
            ->with(
                '{"code":500,"message":"Internal Serveur Error"}',
                ErrorInterface::class,
                'json',
                ['response' => $response, 'responseDefinition' => $responseDefinition, 'request' => $request]
            )
            ->willReturn($error)
        ;

        $this->schema
            ->expects($this->once())
            ->method('getRequestDefinition')
            ->with('getUserItem')
            ->willReturn($requestDefinition)
        ;
        $this->schema->expects($this->never())->method('getSchemes');
        $this->schema->expects($this->never())->method('getHost');

        $this->uriTemplate
            ->expects($this->once())
            ->method('expand')
            ->with('/users/{identifier}', ['identifier' => '48200696-abbf-4787-9615-d8b7955eaee9'])
            ->willReturn('/users/48200696-abbf-4787-9615-d8b7955eaee9')
        ;

        $this->config['baseUri'] = 'https://example.org';
        $this->config['validateResponse'] = true;

        $service = $this->getApiService();
        $this->assertSame($error, $service->call('getUserItem', ['identifier' => '48200696-abbf-4787-9615-d8b7955eaee9']));
        $this->assertSame($this->schema, $service->getSchema());
    }

    /**
     * @dataProvider getModifyMethod
     */
    public function testShouldCallApiForModifyAResourceWithOutError(string $operationId, string $method)
    {
        $requestParameter = $this->createMock(Parameter::class);
        $requestParameter->expects($this->once())->method('getLocation')->willReturn('body');

        $requestParameters = $this->createMock(Parameters::class);
        $requestParameters->expects($this->once())->method('getByName')->with('user')->willReturn($requestParameter);

        $responseDefinition = $this->createMock(ResponseDefinition::class);
        $responseDefinition->expects($this->once())->method('hasBodySchema')->willReturn(true);

        $requestDefinition = $this->createMock(RequestDefinition::class);
        $requestDefinition->expects($this->never())->method('getOperationId');
        $requestDefinition
            ->expects($this->once())
            ->method('getContentTypes')
            ->willReturn(['application/json'])
        ;
        $requestDefinition->expects($this->once())->method('getRequestParameters')->willReturn($requestParameters);
        $requestDefinition->expects($this->exactly(2))->method('getMethod')->willReturn($method);
        $requestDefinition->expects($this->once())->method('getAccepts')->willReturn(['application/json']);
        $requestDefinition->expects($this->once())->method('getPathTemplate')->willReturn('/users/{identifier}');
        $requestDefinition->expects($this->once())->method('getResponseDefinition')->with(200)->willReturn($responseDefinition);

        $uri = $this->createMock(UriInterface::class);
        $uri->expects($this->once())->method('withPath')->with('/users/48200696-abbf-4787-9615-d8b7955eaee9')->willReturnSelf();
        $uri->expects($this->once())->method('withQuery')->with('')->willReturnSelf();

        $this->uriFactory->expects($this->once())->method('createUri')->with('https://example.org')->willReturn($uri);

        $request = $this->createMock(Request::class);

        $this->messageFactory
            ->expects($this->once())
            ->method('createRequest')
            ->with(
                $method,
                $uri,
                ['Content-Type' => 'application/json', 'Accept' => 'application/json'],
                '{"username":"bar@exemple.org"}'
            )
            ->willReturn($request)
        ;

        $body = $this->createMock(StreamInterface::class);
        $body->expects($this->once())->method('__toString')->willReturn('{"username":"bar@exemple.org"}');

        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->exactly(2))->method('getStatusCode')->willReturn(200);
        $response->expects($this->exactly(2))->method('getHeaderLine')->with('Content-Type')->willReturn('application/json');
        $response->expects($this->once())->method('getBody')->willReturn($body);

        $this->httpClient->expects($this->once())->method('sendRequest')->with($request)->willReturn($response);

        $this->messageValidator->expects($this->once())->method('validateRequest')->with($request, $requestDefinition);
        $this->messageValidator->expects($this->exactly(2))->method('hasViolations')->willReturn(false);
        $this->messageValidator->expects($this->never())->method('getViolations');
        $this->messageValidator->expects($this->once())->method('validateResponse')->with($response, $requestDefinition);

        $item = $this->createMock(Item::class);

        $this->serializer
            ->expects($this->once())
            ->method('serialize')
            ->with(['username' => 'bar@exemple.org'], 'json')
            ->willReturn('{"username":"bar@exemple.org"}')
        ;
        $this->serializer
            ->expects($this->once())
            ->method('deserialize')
            ->with(
                '{"username":"bar@exemple.org"}',
                ResourceInterface::class,
                'json',
                ['response' => $response, 'responseDefinition' => $responseDefinition, 'request' => $request]
            )
            ->willReturn($item)
        ;

        $this->schema
            ->expects($this->once())
            ->method('getRequestDefinition')
            ->with($operationId)
            ->willReturn($requestDefinition)
        ;
        $this->schema->expects($this->never())->method('getSchemes');
        $this->schema->expects($this->never())->method('getHost');

        $this->uriTemplate
            ->expects($this->once())
            ->method('expand')
            ->with('/users/{identifier}', [])
            ->willReturn('/users/48200696-abbf-4787-9615-d8b7955eaee9')
        ;

        $this->config['baseUri'] = 'https://example.org';
        $this->config['validateResponse'] = true;

        $service = $this->getApiService();
        $this->assertSame(
            $item,
            $service->call(
                $operationId,
                ['user' => ['username' => 'bar@exemple.org']]
            )
        );
        $this->assertSame($this->schema, $service->getSchema());
    }

    /**
     * @dataProvider getModifyMethod
     */
    public function testShouldCallApiForModifyAResourceWithError(string $operationId, string $method)
    {
        $requestParameter = $this->createMock(Parameter::class);
        $requestParameter->expects($this->once())->method('getLocation')->willReturn('body');

        $requestParameters = $this->createMock(Parameters::class);
        $requestParameters->expects($this->once())->method('getByName')->with('user')->willReturn($requestParameter);

        $responseDefinition = $this->createMock(ResponseDefinition::class);
        $responseDefinition->expects($this->once())->method('hasBodySchema')->willReturn(true);

        $requestDefinition = $this->createMock(RequestDefinition::class);
        $requestDefinition->expects($this->never())->method('getOperationId');
        $requestDefinition
            ->expects($this->once())
            ->method('getContentTypes')
            ->willReturn(['application/json'])
        ;
        $requestDefinition->expects($this->once())->method('getRequestParameters')->willReturn($requestParameters);
        $requestDefinition->expects($this->exactly(2))->method('getMethod')->willReturn($method);
        $requestDefinition->expects($this->once())->method('getAccepts')->willReturn(['application/json']);
        $requestDefinition->expects($this->once())->method('getPathTemplate')->willReturn('/users/{identifier}');
        $requestDefinition->expects($this->once())->method('getResponseDefinition')->with(500)->willReturn($responseDefinition);

        $uri = $this->createMock(UriInterface::class);
        $uri->expects($this->once())->method('withPath')->with('/users/48200696-abbf-4787-9615-d8b7955eaee9')->willReturnSelf();
        $uri->expects($this->once())->method('withQuery')->with('')->willReturnSelf();

        $this->uriFactory->expects($this->once())->method('createUri')->with('https://example.org')->willReturn($uri);

        $request = $this->createMock(Request::class);

        $this->messageFactory
            ->expects($this->once())
            ->method('createRequest')
            ->with(
                $method,
                $uri,
                ['Content-Type' => 'application/json', 'Accept' => 'application/json'],
                '{"username":"bar@exemple.org"}'
            )
            ->willReturn($request)
        ;

        $body = $this->createMock(StreamInterface::class);
        $body->expects($this->once())->method('__toString')->willReturn('{"code":500,"message":"Internal Server Error"}');

        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->exactly(2))->method('getStatusCode')->willReturn(500);
        $response->expects($this->exactly(2))->method('getHeaderLine')->with('Content-Type')->willReturn('application/json');
        $response->expects($this->once())->method('getBody')->willReturn($body);

        $this->httpClient->expects($this->once())->method('sendRequest')->with($request)->willReturn($response);

        $this->messageValidator->expects($this->once())->method('validateRequest')->with($request, $requestDefinition);
        $this->messageValidator->expects($this->exactly(2))->method('hasViolations')->willReturn(false);
        $this->messageValidator->expects($this->never())->method('getViolations');
        $this->messageValidator->expects($this->once())->method('validateResponse')->with($response, $requestDefinition);

        $error = $this->createMock(ErrorInterface::class);

        $this->serializer
            ->expects($this->once())
            ->method('serialize')
            ->with(['username' => 'bar@exemple.org'], 'json')
            ->willReturn('{"username":"bar@exemple.org"}')
        ;
        $this->serializer
            ->expects($this->once())
            ->method('deserialize')
            ->with(
                '{"code":500,"message":"Internal Server Error"}',
                ErrorInterface::class,
                'json',
                ['response' => $response, 'responseDefinition' => $responseDefinition, 'request' => $request]
            )
            ->willReturn($error)
        ;

        $this->schema
            ->expects($this->once())
            ->method('getRequestDefinition')
            ->with($operationId)
            ->willReturn($requestDefinition)
        ;
        $this->schema->expects($this->never())->method('getSchemes');
        $this->schema->expects($this->never())->method('getHost');

        $this->uriTemplate
            ->expects($this->once())
            ->method('expand')
            ->with('/users/{identifier}', [])
            ->willReturn('/users/48200696-abbf-4787-9615-d8b7955eaee9')
        ;

        $this->config['baseUri'] = 'https://example.org';
        $this->config['validateResponse'] = true;

        $service = $this->getApiService();
        $this->assertSame(
            $error,
            $service->call(
                $operationId,
                ['user' => ['username' => 'bar@exemple.org']]
            )
        );
        $this->assertSame($this->schema, $service->getSchema());
    }

    public function getModifyMethod(): array
    {
        return [
            ['PUT', 'putUserItem'],
            ['PATCH', 'patchUserItem'],
        ];
    }

    public function testShouldCallApiForDeleteAResourceWithOutError()
    {
        $requestParameter = $this->createMock(Parameter::class);
        $requestParameter->expects($this->once())->method('getLocation')->willReturn('body');

        $requestParameters = $this->createMock(Parameters::class);
        $requestParameters->expects($this->once())->method('getByName')->with('user')->willReturn($requestParameter);

        $responseDefinition = $this->createMock(ResponseDefinition::class);
        $responseDefinition->expects($this->once())->method('hasBodySchema')->willReturn(false);

        $requestDefinition = $this->createMock(RequestDefinition::class);
        $requestDefinition->expects($this->never())->method('getOperationId');
        $requestDefinition
            ->expects($this->once())
            ->method('getContentTypes')
            ->willReturn(['application/json'])
        ;
        $requestDefinition->expects($this->once())->method('getRequestParameters')->willReturn($requestParameters);
        $requestDefinition->expects($this->exactly(2))->method('getMethod')->willReturn('DELETE');
        $requestDefinition->expects($this->once())->method('getAccepts')->willReturn(['application/json']);
        $requestDefinition->expects($this->once())->method('getPathTemplate')->willReturn('/users/{identifier}');
        $requestDefinition->expects($this->once())->method('getResponseDefinition')->with(204)->willReturn($responseDefinition);

        $uri = $this->createMock(UriInterface::class);
        $uri->expects($this->once())->method('withPath')->with('/users/48200696-abbf-4787-9615-d8b7955eaee9')->willReturnSelf();
        $uri->expects($this->once())->method('withQuery')->with('')->willReturnSelf();

        $this->uriFactory->expects($this->once())->method('createUri')->with('https://example.org')->willReturn($uri);

        $request = $this->createMock(Request::class);

        $this->messageFactory
            ->expects($this->once())
            ->method('createRequest')
            ->with(
                'DELETE',
                $uri,
                ['Content-Type' => 'application/json', 'Accept' => 'application/json'],
                '{"username":"bar@exemple.org"}'
            )
            ->willReturn($request)
        ;

        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->once())->method('getStatusCode')->willReturn(204);
        $response->expects($this->never())->method('getHeaderLine');
        $response->expects($this->never())->method('getBody');

        $this->httpClient->expects($this->once())->method('sendRequest')->with($request)->willReturn($response);

        $this->messageValidator->expects($this->once())->method('validateRequest')->with($request, $requestDefinition);
        $this->messageValidator->expects($this->exactly(2))->method('hasViolations')->willReturn(false);
        $this->messageValidator->expects($this->never())->method('getViolations');
        $this->messageValidator->expects($this->once())->method('validateResponse')->with($response, $requestDefinition);

        $item = $this->createMock(Item::class);

        $this->serializer
            ->expects($this->once())
            ->method('serialize')
            ->with(['username' => 'bar@exemple.org'], 'json')
            ->willReturn('{"username":"bar@exemple.org"}')
        ;
        $this->serializer->expects($this->never())->method('deserialize');

        $this->schema
            ->expects($this->once())
            ->method('getRequestDefinition')
            ->with('deleteUserItem')
            ->willReturn($requestDefinition)
        ;
        $this->schema->expects($this->never())->method('getSchemes');
        $this->schema->expects($this->never())->method('getHost');

        $this->uriTemplate
            ->expects($this->once())
            ->method('expand')
            ->with('/users/{identifier}', [])
            ->willReturn('/users/48200696-abbf-4787-9615-d8b7955eaee9')
        ;

        $this->config['baseUri'] = 'https://example.org';
        $this->config['validateResponse'] = true;

        $service = $this->getApiService();
        $this->assertSame(
            null,
            $service->call(
                'deleteUserItem',
                ['user' => ['username' => 'bar@exemple.org']]
            )
        );
        $this->assertSame($this->schema, $service->getSchema());
    }

    public function testShouldNotCallApiInGetBecauseParameterIsNotFound()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('foo is not a defined request parameter for operationId ');

        $requestParameters = $this->createMock(Parameters::class);
        $requestParameters->expects($this->once())->method('getByName')->with('foo')->willReturn(null);

        $requestDefinition = $this->createMock(RequestDefinition::class);
        $requestDefinition->expects($this->once())->method('getOperationId')->willReturn('getUserCollection');
        $requestDefinition->expects($this->once())->method('getContentTypes')->willReturn(['application/hal+json', 'application/json']);
        $requestDefinition->expects($this->once())->method('getRequestParameters')->willReturn($requestParameters);
        $requestDefinition->expects($this->once())->method('getMethod')->willReturn('GET');
        $requestDefinition->expects($this->once())->method('getAccepts')->willReturn(['application/hal+json', 'application/ld+json']);
        $requestDefinition->expects($this->never())->method('getPathTemplate');
        $requestDefinition->expects($this->never())->method('getResponseDefinition');

        $uri = $this->createMock(UriInterface::class);
        $uri->expects($this->never())->method('withPath');
        $uri->expects($this->never())->method('withQuery');

        $this->uriFactory->expects($this->once())->method('createUri')->with('https://example.org')->willReturn($uri);

        $this->messageFactory->expects($this->never())->method('createRequest');

        $this->httpClient->expects($this->never())->method('sendRequest');

        $this->messageValidator->expects($this->never())->method('validateRequest');
        $this->messageValidator->expects($this->never())->method('hasViolations');
        $this->messageValidator->expects($this->never())->method('getViolations');
        $this->messageValidator->expects($this->never())->method('validateResponse');

        $collection = $this->createMock(Collection::class);

        $this->serializer->expects($this->never())->method('serialize');
        $this->serializer->expects($this->never())->method('deserialize');

        $this->schema
            ->expects($this->once())
            ->method('getRequestDefinition')
            ->with('getUserCollection')
            ->willReturn($requestDefinition)
        ;
        $this->schema->expects($this->never())->method('getSchemes');
        $this->schema->expects($this->never())->method('getHost');

        $this->uriTemplate->expects($this->never())->method('expand');

        $this->config['baseUri'] = 'https://example.org';
        $this->config['validateResponse'] = true;

        $service = $this->getApiService();
        $this->assertSame($collection, $service->call('getUserCollection', ['foo' => 'bar']));
        $this->assertSame($this->schema, $service->getSchema());
    }

    public function testShouldNotCallApiInGetBecauseThereAreSomeViolationInRequest()
    {
        try {
            $requestParameter = $this->createMock(Parameter::class);
            $requestParameter->expects($this->once())->method('getLocation')->willReturn('query');

            $requestParameters = $this->createMock(Parameters::class);
            $requestParameters->expects($this->once())->method('getByName')->with('foo')->willReturn($requestParameter);

            $requestDefinition = $this->createMock(RequestDefinition::class);
            $requestDefinition->expects($this->never())->method('getOperationId');
            $requestDefinition
                ->expects($this->once())
                ->method('getContentTypes')
                ->willReturn(['application/hal+json', 'application/json'])
            ;
            $requestDefinition->expects($this->once())->method('getRequestParameters')->willReturn($requestParameters);
            $requestDefinition->expects($this->exactly(2))->method('getMethod')->willReturn('GET');
            $requestDefinition->expects($this->once())->method('getAccepts')->willReturn(['application/json', 'application/ld+json']);
            $requestDefinition->expects($this->once())->method('getPathTemplate')->willReturn('/users');
            $requestDefinition->expects($this->never())->method('getResponseDefinition');

            $uri = $this->createMock(UriInterface::class);
            $uri->expects($this->once())->method('withPath')->with('/users')->willReturnSelf();
            $uri->expects($this->once())->method('withQuery')->with('foo=bar')->willReturnSelf();

            $this->uriFactory->expects($this->once())->method('createUri')->with('https://example.org')->willReturn($uri);

            $request = $this->createMock(Request::class);

            $this->messageFactory
                ->expects($this->once())
                ->method('createRequest')
                ->with(
                    'GET',
                    $uri,
                    ['Content-Type' => 'application/hal+json', 'Accept' => 'application/json'],
                    null
                )
                ->willReturn($request)
            ;

            $this->httpClient->expects($this->never())->method('sendRequest');

            $this->messageValidator->expects($this->once())->method('validateRequest')->with($request, $requestDefinition);
            $this->messageValidator->expects($this->once())->method('hasViolations')->willReturn(true);
            $this->messageValidator->expects($this->once())->method('getViolations')->willReturn([]);
            $this->messageValidator->expects($this->never())->method('validateResponse');

            $collection = $this->createMock(Collection::class);

            $this->serializer->expects($this->never())->method('serialize');
            $this->serializer->expects($this->never())->method('deserialize');

            $this->schema
                ->expects($this->once())
                ->method('getRequestDefinition')
                ->with('getUserCollection')
                ->willReturn($requestDefinition)
            ;
            $this->schema->expects($this->never())->method('getSchemes');
            $this->schema->expects($this->never())->method('getHost');

            $this->uriTemplate->expects($this->once())->method('expand')->with('/users', [])->willReturn('/users');

            $this->config['baseUri'] = 'https://example.org';
            $this->config['validateResponse'] = true;

            $service = $this->getApiService();
            $this->assertSame($this->schema, $service->getSchema());
            $this->assertSame($collection, $service->call('getUserCollection', ['foo' => 'bar']));

            $this->fail('This test must throw a request exception');
        } catch (\Exception $e) {
            $this->assertInstanceOf(RequestViolations::class, $e);
            $this->assertSame([], $e->getViolations());
        }
    }

    public function testShouldCallApiInGetWButThereAreSomeViolationInResponse()
    {
        try {
            $requestParameter = $this->createMock(Parameter::class);
            $requestParameter->expects($this->once())->method('getLocation')->willReturn('query');

            $requestParameters = $this->createMock(Parameters::class);
            $requestParameters->expects($this->once())->method('getByName')->with('foo')->willReturn($requestParameter);

            $requestDefinition = $this->createMock(RequestDefinition::class);
            $requestDefinition->expects($this->never())->method('getOperationId');
            $requestDefinition
                ->expects($this->once())
                ->method('getContentTypes')
                ->willReturn(['application/hal+json', 'application/json'])
            ;
            $requestDefinition->expects($this->once())->method('getRequestParameters')->willReturn($requestParameters);
            $requestDefinition->expects($this->exactly(2))->method('getMethod')->willReturn('GET');
            $requestDefinition->expects($this->once())->method('getAccepts')->willReturn(['application/json', 'application/ld+json']);
            $requestDefinition->expects($this->once())->method('getPathTemplate')->willReturn('/users');
            $requestDefinition->expects($this->never())->method('getResponseDefinition');

            $uri = $this->createMock(UriInterface::class);
            $uri->expects($this->once())->method('withPath')->with('/users')->willReturnSelf();
            $uri->expects($this->once())->method('withQuery')->with('foo=bar')->willReturnSelf();

            $this->uriFactory->expects($this->once())->method('createUri')->with('https://example.org')->willReturn($uri);

            $request = $this->createMock(Request::class);

            $this->messageFactory
                ->expects($this->once())
                ->method('createRequest')
                ->with(
                    'GET',
                    $uri,
                    ['Content-Type' => 'application/hal+json', 'Accept' => 'application/json'],
                    null
                )
                ->willReturn($request)
            ;

            $response = $this->createMock(ResponseInterface::class);
            $response->expects($this->never())->method('getStatusCode');
            $response->expects($this->never())->method('getHeaderLine');
            $response->expects($this->never())->method('getBody');

            $this->httpClient->expects($this->once())->method('sendRequest')->with($request)->willReturn($response);

            $this->messageValidator->expects($this->once())->method('validateRequest')->with($request, $requestDefinition);
            $this->messageValidator->expects($this->exactly(2))->method('hasViolations')->willReturnOnConsecutiveCalls(false, true);
            $this->messageValidator->expects($this->once())->method('getViolations')->willReturn([]);
            $this->messageValidator->expects($this->once())->method('validateResponse')->with($response, $requestDefinition);

            $this->serializer->expects($this->never())->method('serialize');
            $this->serializer->expects($this->never())->method('deserialize');

            $this->schema
                ->expects($this->once())
                ->method('getRequestDefinition')
                ->with('getUserCollection')
                ->willReturn($requestDefinition)
            ;
            $this->schema->expects($this->never())->method('getSchemes');
            $this->schema->expects($this->never())->method('getHost');

            $this->uriTemplate->expects($this->once())->method('expand')->with('/users', [])->willReturn('/users');

            $this->config['baseUri'] = 'https://example.org';
            $this->config['validateResponse'] = true;

            $service = $this->getApiService();
            $this->assertSame($this->schema, $service->getSchema());
            $service->call('getUserCollection', ['foo' => 'bar']);
            $this->fail('This test must throw an Exception');
        } catch (\Exception $e) {
            $this->assertInstanceOf(ResponseViolations::class, $e);
            $this->assertSame([], $e->getViolations());
        }
    }

    public function testShouldCallApiInGetWithOutErrorButWithOutCheckRequest()
    {
        $requestParameter = $this->createMock(Parameter::class);
        $requestParameter->expects($this->once())->method('getLocation')->willReturn('query');

        $requestParameters = $this->createMock(Parameters::class);
        $requestParameters->expects($this->once())->method('getByName')->with('foo')->willReturn($requestParameter);

        $responseDefinition = $this->createMock(ResponseDefinition::class);
        $responseDefinition->expects($this->once())->method('hasBodySchema')->willReturn(true);

        $requestDefinition = $this->createMock(RequestDefinition::class);
        $requestDefinition->expects($this->never())->method('getOperationId');
        $requestDefinition
            ->expects($this->once())
            ->method('getContentTypes')
            ->willReturn(['application/hal+json', 'application/json'])
        ;
        $requestDefinition->expects($this->once())->method('getRequestParameters')->willReturn($requestParameters);
        $requestDefinition->expects($this->exactly(2))->method('getMethod')->willReturn('GET');
        $requestDefinition->expects($this->once())->method('getAccepts')->willReturn(['application/json', 'application/ld+json']);
        $requestDefinition->expects($this->once())->method('getPathTemplate')->willReturn('/users');
        $requestDefinition->expects($this->once())->method('getResponseDefinition')->with(200)->willReturn($responseDefinition);

        $uri = $this->createMock(UriInterface::class);
        $uri->expects($this->once())->method('withPath')->with('/users')->willReturnSelf();
        $uri->expects($this->once())->method('withQuery')->with('foo=bar')->willReturnSelf();

        $this->uriFactory->expects($this->once())->method('createUri')->with('https://example.org')->willReturn($uri);

        $request = $this->createMock(Request::class);

        $this->messageFactory
            ->expects($this->once())
            ->method('createRequest')
            ->with(
                'GET',
                $uri,
                ['Content-Type' => 'application/hal+json', 'Accept' => 'application/json'],
                null
            )
            ->willReturn($request)
        ;

        $body = $this->createMock(StreamInterface::class);
        $body->expects($this->once())->method('__toString')->willReturn('[]');

        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->exactly(2))->method('getStatusCode')->willReturn(200);
        $response->expects($this->exactly(2))->method('getHeaderLine')->with('Content-Type')->willReturn('application/hal+json');
        $response->expects($this->once())->method('getBody')->willReturn($body);

        $this->httpClient->expects($this->once())->method('sendRequest')->with($request)->willReturn($response);

        $this->messageValidator->expects($this->never())->method('validateRequest');
        $this->messageValidator->expects($this->once())->method('hasViolations')->willReturn(false);
        $this->messageValidator->expects($this->never())->method('getViolations');
        $this->messageValidator->expects($this->once())->method('validateResponse')->with($response, $requestDefinition);

        $collection = $this->createMock(Collection::class);

        $this->serializer->expects($this->never())->method('serialize');
        $this->serializer
            ->expects($this->once())
            ->method('deserialize')
            ->with(
                '[]',
                ResourceInterface::class,
                'hal+json',
                ['response' => $response, 'responseDefinition' => $responseDefinition, 'request' => $request]
            )
            ->willReturn($collection)
        ;

        $this->schema
            ->expects($this->once())
            ->method('getRequestDefinition')
            ->with('getUserCollection')
            ->willReturn($requestDefinition)
        ;
        $this->schema->expects($this->never())->method('getSchemes');
        $this->schema->expects($this->never())->method('getHost');

        $this->uriTemplate->expects($this->once())->method('expand')->with('/users', [])->willReturn('/users');

        $this->config['baseUri'] = 'https://example.org';
        $this->config['validateRequest'] = false;
        $this->config['validateResponse'] = true;

        $service = $this->getApiService();
        $this->assertSame($collection, $service->call('getUserCollection', ['foo' => 'bar']));
        $this->assertSame($this->schema, $service->getSchema());
    }

    public function testShouldCallApiInGetWithOutErrorButWithOutCheckResponse()
    {
        $requestParameter = $this->createMock(Parameter::class);
        $requestParameter->expects($this->once())->method('getLocation')->willReturn('query');

        $requestParameters = $this->createMock(Parameters::class);
        $requestParameters->expects($this->once())->method('getByName')->with('foo')->willReturn($requestParameter);

        $responseDefinition = $this->createMock(ResponseDefinition::class);
        $responseDefinition->expects($this->once())->method('hasBodySchema')->willReturn(true);

        $requestDefinition = $this->createMock(RequestDefinition::class);
        $requestDefinition->expects($this->never())->method('getOperationId');
        $requestDefinition
            ->expects($this->once())
            ->method('getContentTypes')
            ->willReturn(['application/hal+json', 'application/json'])
        ;
        $requestDefinition->expects($this->once())->method('getRequestParameters')->willReturn($requestParameters);
        $requestDefinition->expects($this->exactly(2))->method('getMethod')->willReturn('GET');
        $requestDefinition->expects($this->once())->method('getAccepts')->willReturn(['application/json', 'application/ld+json']);
        $requestDefinition->expects($this->once())->method('getPathTemplate')->willReturn('/users');
        $requestDefinition->expects($this->once())->method('getResponseDefinition')->with(200)->willReturn($responseDefinition);

        $uri = $this->createMock(UriInterface::class);
        $uri->expects($this->once())->method('withPath')->with('/users')->willReturnSelf();
        $uri->expects($this->once())->method('withQuery')->with('foo=bar')->willReturnSelf();

        $this->uriFactory->expects($this->once())->method('createUri')->with('https://example.org')->willReturn($uri);

        $request = $this->createMock(Request::class);

        $this->messageFactory
            ->expects($this->once())
            ->method('createRequest')
            ->with(
                'GET',
                $uri,
                ['Content-Type' => 'application/hal+json', 'Accept' => 'application/json'],
                null
            )
            ->willReturn($request)
        ;

        $body = $this->createMock(StreamInterface::class);
        $body->expects($this->once())->method('__toString')->willReturn('[]');

        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->exactly(2))->method('getStatusCode')->willReturn(200);
        $response->expects($this->exactly(2))->method('getHeaderLine')->with('Content-Type')->willReturn('application/hal+json');
        $response->expects($this->once())->method('getBody')->willReturn($body);

        $this->httpClient->expects($this->once())->method('sendRequest')->with($request)->willReturn($response);

        $this->messageValidator->expects($this->once())->method('validateRequest')->with($request, $requestDefinition);
        $this->messageValidator->expects($this->once())->method('hasViolations')->willReturn(false);
        $this->messageValidator->expects($this->never())->method('getViolations');
        $this->messageValidator->expects($this->never())->method('validateResponse');

        $collection = $this->createMock(Collection::class);

        $this->serializer->expects($this->never())->method('serialize');
        $this->serializer
            ->expects($this->once())
            ->method('deserialize')
            ->with(
                '[]',
                ResourceInterface::class,
                'hal+json',
                ['response' => $response, 'responseDefinition' => $responseDefinition, 'request' => $request]
            )
            ->willReturn($collection)
        ;

        $this->schema
            ->expects($this->once())
            ->method('getRequestDefinition')
            ->with('getUserCollection')
            ->willReturn($requestDefinition)
        ;
        $this->schema->expects($this->never())->method('getSchemes');
        $this->schema->expects($this->never())->method('getHost');

        $this->uriTemplate->expects($this->once())->method('expand')->with('/users', [])->willReturn('/users');

        $this->config['baseUri'] = 'https://example.org';
        $this->config['validateResponse'] = false;

        $service = $this->getApiService();
        $this->assertSame($collection, $service->call('getUserCollection', ['foo' => 'bar']));
        $this->assertSame($this->schema, $service->getSchema());
    }

    public function testShouldCallApiInGetWithOutError()
    {
        $requestParameter = $this->createMock(Parameter::class);
        $requestParameter->expects($this->once())->method('getLocation')->willReturn('query');

        $requestParameters = $this->createMock(Parameters::class);
        $requestParameters->expects($this->once())->method('getByName')->with('foo')->willReturn($requestParameter);

        $responseDefinition = $this->createMock(ResponseDefinition::class);
        $responseDefinition->expects($this->once())->method('hasBodySchema')->willReturn(true);

        $requestDefinition = $this->createMock(RequestDefinition::class);
        $requestDefinition->expects($this->never())->method('getOperationId');
        $requestDefinition
            ->expects($this->once())
            ->method('getContentTypes')
            ->willReturn(['application/hal+json', 'application/json'])
        ;
        $requestDefinition->expects($this->once())->method('getRequestParameters')->willReturn($requestParameters);
        $requestDefinition->expects($this->exactly(2))->method('getMethod')->willReturn('GET');
        $requestDefinition->expects($this->once())->method('getAccepts')->willReturn(['application/json', 'application/ld+json']);
        $requestDefinition->expects($this->once())->method('getPathTemplate')->willReturn('/users');
        $requestDefinition->expects($this->once())->method('getResponseDefinition')->with(200)->willReturn($responseDefinition);

        $uri = $this->createMock(UriInterface::class);
        $uri->expects($this->once())->method('withPath')->with('/users')->willReturnSelf();
        $uri->expects($this->once())->method('withQuery')->with('foo=bar')->willReturnSelf();

        $this->uriFactory->expects($this->once())->method('createUri')->with('https://example.org')->willReturn($uri);

        $request = $this->createMock(Request::class);

        $this->messageFactory
            ->expects($this->once())
            ->method('createRequest')
            ->with(
                'GET',
                $uri,
                ['Content-Type' => 'application/hal+json', 'Accept' => 'application/json'],
                null
            )
            ->willReturn($request)
        ;

        $body = $this->createMock(StreamInterface::class);
        $body->expects($this->once())->method('__toString')->willReturn('[]');

        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->exactly(2))->method('getStatusCode')->willReturn(200);
        $response->expects($this->exactly(2))->method('getHeaderLine')->with('Content-Type')->willReturn('application/hal+json');
        $response->expects($this->once())->method('getBody')->willReturn($body);

        $this->httpClient->expects($this->once())->method('sendRequest')->with($request)->willReturn($response);

        $this->messageValidator->expects($this->once())->method('validateRequest')->with($request, $requestDefinition);
        $this->messageValidator->expects($this->exactly(2))->method('hasViolations')->willReturn(false);
        $this->messageValidator->expects($this->never())->method('getViolations');
        $this->messageValidator->expects($this->once())->method('validateResponse')->with($response, $requestDefinition);

        $collection = $this->createMock(Collection::class);

        $this->serializer->expects($this->never())->method('serialize');
        $this->serializer
            ->expects($this->once())
            ->method('deserialize')
            ->with(
                '[]',
                ResourceInterface::class,
                'hal+json',
                ['response' => $response, 'responseDefinition' => $responseDefinition, 'request' => $request]
            )
            ->willReturn($collection)
        ;

        $this->schema
            ->expects($this->once())
            ->method('getRequestDefinition')
            ->with('getUserCollection')
            ->willReturn($requestDefinition)
        ;
        $this->schema->expects($this->never())->method('getSchemes');
        $this->schema->expects($this->never())->method('getHost');

        $this->uriTemplate->expects($this->once())->method('expand')->with('/users', [])->willReturn('/users');

        $this->config['baseUri'] = 'https://example.org';
        $this->config['validateResponse'] = true;

        $service = $this->getApiService();
        $this->assertSame($collection, $service->call('getUserCollection', ['foo' => 'bar']));
        $this->assertSame($this->schema, $service->getSchema());
    }

    public function testShouldCallApiInGetWithOutErrorButReturnResponse()
    {
        $requestParameter = $this->createMock(Parameter::class);
        $requestParameter->expects($this->once())->method('getLocation')->willReturn('query');

        $requestParameters = $this->createMock(Parameters::class);
        $requestParameters->expects($this->once())->method('getByName')->with('foo')->willReturn($requestParameter);

        $responseDefinition = $this->createMock(ResponseDefinition::class);
        $responseDefinition->expects($this->never())->method('hasBodySchema');

        $requestDefinition = $this->createMock(RequestDefinition::class);
        $requestDefinition->expects($this->never())->method('getOperationId');
        $requestDefinition
            ->expects($this->once())
            ->method('getContentTypes')
            ->willReturn(['application/hal+json', 'application/json'])
        ;
        $requestDefinition->expects($this->once())->method('getRequestParameters')->willReturn($requestParameters);
        $requestDefinition->expects($this->exactly(2))->method('getMethod')->willReturn('GET');
        $requestDefinition->expects($this->once())->method('getAccepts')->willReturn(['application/json', 'application/ld+json']);
        $requestDefinition->expects($this->once())->method('getPathTemplate')->willReturn('/users');
        $requestDefinition->expects($this->once())->method('getResponseDefinition')->with(200)->willReturn($responseDefinition);

        $uri = $this->createMock(UriInterface::class);
        $uri->expects($this->once())->method('withPath')->with('/users')->willReturnSelf();
        $uri->expects($this->once())->method('withQuery')->with('foo=bar')->willReturnSelf();

        $this->uriFactory->expects($this->once())->method('createUri')->with('https://example.org')->willReturn($uri);

        $request = $this->createMock(Request::class);

        $this->messageFactory
            ->expects($this->once())
            ->method('createRequest')
            ->with(
                'GET',
                $uri,
                ['Content-Type' => 'application/hal+json', 'Accept' => 'application/json'],
                null
            )
            ->willReturn($request)
        ;

        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->once())->method('getStatusCode')->willReturn(200);
        $response->expects($this->never())->method('getHeaderLine');
        $response->expects($this->never())->method('getBody');

        $this->httpClient->expects($this->once())->method('sendRequest')->with($request)->willReturn($response);

        $this->messageValidator->expects($this->once())->method('validateRequest')->with($request, $requestDefinition);
        $this->messageValidator->expects($this->exactly(2))->method('hasViolations')->willReturn(false);
        $this->messageValidator->expects($this->never())->method('getViolations');
        $this->messageValidator->expects($this->once())->method('validateResponse')->with($response, $requestDefinition);

        $this->serializer->expects($this->never())->method('serialize');
        $this->serializer->expects($this->never())->method('deserialize');

        $this->schema
            ->expects($this->once())
            ->method('getRequestDefinition')
            ->with('getUserCollection')
            ->willReturn($requestDefinition)
        ;
        $this->schema->expects($this->never())->method('getSchemes');
        $this->schema->expects($this->never())->method('getHost');

        $this->uriTemplate->expects($this->once())->method('expand')->with('/users', [])->willReturn('/users');

        $this->config['baseUri'] = 'https://example.org';
        $this->config['validateResponse'] = true;

        $service = $this->getApiService();
        $newService = $service->withReturnResponse(true);
        $this->assertNotSame($newService, $service);
        $this->assertSame($response, $newService->call('getUserCollection', ['foo' => 'bar']));
        $this->assertSame($this->schema, $service->getSchema());
    }

    public function testShouldCallApiInGetWithErrors()
    {
        $requestParameter = $this->createMock(Parameter::class);
        $requestParameter->expects($this->once())->method('getLocation')->willReturn('query');

        $requestParameters = $this->createMock(Parameters::class);
        $requestParameters->expects($this->once())->method('getByName')->with('foo')->willReturn($requestParameter);

        $responseDefinition = $this->createMock(ResponseDefinition::class);
        $responseDefinition->expects($this->once())->method('hasBodySchema')->willReturn(true);

        $requestDefinition = $this->createMock(RequestDefinition::class);
        $requestDefinition->expects($this->never())->method('getOperationId');
        $requestDefinition
            ->expects($this->once())
            ->method('getContentTypes')
            ->willReturn(['application/hal+json', 'application/json'])
        ;
        $requestDefinition->expects($this->once())->method('getRequestParameters')->willReturn($requestParameters);
        $requestDefinition->expects($this->exactly(2))->method('getMethod')->willReturn('GET');
        $requestDefinition->expects($this->once())->method('getAccepts')->willReturn(['application/json', 'application/ld+json']);
        $requestDefinition->expects($this->once())->method('getPathTemplate')->willReturn('/users');
        $requestDefinition->expects($this->once())->method('getResponseDefinition')->with(400)->willReturn($responseDefinition);

        $this->schema
            ->expects($this->once())
            ->method('getRequestDefinition')
            ->with('getUserCollection')
            ->willReturn($requestDefinition)
        ;

        $uri = $this->createMock(UriInterface::class);
        $uri->expects($this->once())->method('withPath')->with('/users')->willReturnSelf();
        $uri->expects($this->once())->method('withQuery')->with('foo=bar')->willReturnSelf();

        $this->uriFactory->expects($this->once())->method('createUri')->with('https://example.org')->willReturn($uri);

        $request = $this->createMock(Request::class);

        $this->messageFactory
            ->expects($this->once())
            ->method('createRequest')
            ->with(
                'GET',
                $uri,
                ['Content-Type' => 'application/hal+json', 'Accept' => 'application/json'],
                null
            )
            ->willReturn($request)
        ;

        $body = $this->createMock(StreamInterface::class);
        $body->expects($this->once())->method('__toString')->willReturn('[]');

        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->exactly(2))->method('getStatusCode')->willReturn(400);
        $response->expects($this->exactly(2))->method('getHeaderLine')->with('Content-Type')->willReturn('application/hal+json');
        $response->expects($this->once())->method('getBody')->willReturn($body);

        $this->httpClient->expects($this->once())->method('sendRequest')->with($request)->willReturn($response);

        $this->messageValidator->expects($this->once())->method('validateRequest')->with($request, $requestDefinition);
        $this->messageValidator->expects($this->exactly(2))->method('hasViolations')->willReturn(false);
        $this->messageValidator->expects($this->never())->method('getViolations');
        $this->messageValidator->expects($this->once())->method('validateResponse')->with($response, $requestDefinition);

        $error = $this->createMock(ErrorInterface::class);

        $this->serializer->expects($this->never())->method('serialize');
        $this->serializer
            ->expects($this->once())
            ->method('deserialize')
            ->with(
                '[]',
                ErrorInterface::class,
                'hal+json',
                ['response' => $response, 'responseDefinition' => $responseDefinition, 'request' => $request]
            )
            ->willReturn($error)
        ;

        $this->config['baseUri'] = 'https://example.org';
        $this->config['validateResponse'] = true;

        $this->schema->expects($this->never())->method('getSchemes');
        $this->schema->expects($this->never())->method('getHost');

        $this->uriTemplate->expects($this->once())->method('expand')->with('/users', [])->willReturn('/users');

        $this->serializer->expects($this->never())->method('serialize');

        $service = $this->getApiService();
        $this->assertSame($error, $service->call('getUserCollection', ['foo' => 'bar']));
        $this->assertSame($this->schema, $service->getSchema());
    }

    public function testShouldBuildQuery()
    {
        $params = ['exist' => ['foo.bar' => true], 'foo' => 'bar', 'uuid' => ['baz', 'bar']];

        $this->assertSame(
            ['exist[foo.bar]' => true, 'foo' => 'bar', 'uuid' => ['baz', 'bar']],
            ApiService::buildQuery($params)
        );
    }

    public function testShouldNotBuildPromiseBecauseItIsNotAnAsyncClient()
    {
        $this->expectException(\RuntimeException::class);

        $uri = $this->createMock(UriInterface::class);
        $uri->expects($this->never())->method('withPath');
        $uri->expects($this->never())->method('withQuery');

        $this->uriFactory->expects($this->once())->method('createUri')->with('https://example.org')->willReturn($uri);

        $this->messageFactory->expects($this->never())->method('createRequest');

        $this->messageValidator->expects($this->never())->method('validateRequest');
        $this->messageValidator->expects($this->never())->method('hasViolations');
        $this->messageValidator->expects($this->never())->method('getViolations');
        $this->messageValidator->expects($this->never())->method('validateResponse');

        $this->serializer->expects($this->never())->method('serialize');
        $this->serializer->expects($this->never())->method('deserialize');

        $this->schema->expects($this->never())->method('getRequestDefinition');
        $this->schema->expects($this->never())->method('getSchemes');
        $this->schema->expects($this->never())->method('getHost');

        $this->uriTemplate->expects($this->never())->method('expand');

        $this->config['baseUri'] = 'https://example.org';
        $this->config['validateResponse'] = true;

        $service = $this->getApiService();
        $this->assertSame($this->schema, $service->getSchema());
        $promise = $service->callAsync('getUserCollection', ['foo' => 'bar']);
        $this->assertInstanceOf(Promise::class, $promise);
        $promise->wait();
    }

    public function testShouldBuildPromise()
    {
        $requestParameter = $this->createMock(Parameter::class);
        $requestParameter->expects($this->once())->method('getLocation')->willReturn('query');

        $requestParameters = $this->createMock(Parameters::class);
        $requestParameters->expects($this->once())->method('getByName')->with('foo')->willReturn($requestParameter);

        $responseDefinition = $this->createMock(ResponseDefinition::class);
        $responseDefinition->expects($this->once())->method('hasBodySchema')->willReturn(true);

        $requestDefinition = $this->createMock(RequestDefinition::class);
        $requestDefinition->expects($this->never())->method('getOperationId');
        $requestDefinition
            ->expects($this->once())
            ->method('getContentTypes')
            ->willReturn(['application/hal+json', 'application/json'])
        ;
        $requestDefinition->expects($this->once())->method('getRequestParameters')->willReturn($requestParameters);
        $requestDefinition->expects($this->exactly(2))->method('getMethod')->willReturn('GET');
        $requestDefinition->expects($this->once())->method('getAccepts')->willReturn(['application/json', 'application/ld+json']);
        $requestDefinition->expects($this->once())->method('getPathTemplate')->willReturn('/users');
        $requestDefinition->expects($this->once())->method('getResponseDefinition')->with(200)->willReturn($responseDefinition);

        $uri = $this->createMock(UriInterface::class);
        $uri->expects($this->once())->method('withPath')->with('/users')->willReturnSelf();
        $uri->expects($this->once())->method('withQuery')->with('foo=bar')->willReturnSelf();

        $this->uriFactory->expects($this->once())->method('createUri')->with('https://example.org')->willReturn($uri);

        $request = $this->createMock(Request::class);

        $this->messageFactory
            ->expects($this->once())
            ->method('createRequest')
            ->with(
                'GET',
                $uri,
                ['Content-Type' => 'application/hal+json', 'Accept' => 'application/json'],
                null
            )
            ->willReturn($request)
        ;

        $body = $this->createMock(StreamInterface::class);
        $body->expects($this->once())->method('__toString')->willReturn('[]');

        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->exactly(2))->method('getStatusCode')->willReturn(200);
        $response->expects($this->exactly(2))->method('getHeaderLine')->with('Content-Type')->willReturn('application/hal+json');
        $response->expects($this->once())->method('getBody')->willReturn($body);

        $this->httpClient = $this->createMock(HttpAsyncClient::class);
        $this->httpClient->expects($this->once())->method('sendAsyncRequest')->with($request)->willReturn(new FulfilledPromise($response));

        $this->messageValidator->expects($this->never())->method('validateRequest');
        $this->messageValidator->expects($this->never())->method('hasViolations');
        $this->messageValidator->expects($this->never())->method('getViolations');
        $this->messageValidator->expects($this->never())->method('validateResponse');

        $collection = $this->createMock(Collection::class);

        $this->serializer->expects($this->never())->method('serialize');
        $this->serializer
            ->expects($this->once())
            ->method('deserialize')
            ->with(
                '[]',
                ResourceInterface::class,
                'hal+json',
                ['response' => $response, 'responseDefinition' => $responseDefinition, 'request' => $request]
            )
            ->willReturn($collection)
        ;

        $this->schema
            ->expects($this->once())
            ->method('getRequestDefinition')
            ->with('getUserCollection')
            ->willReturn($requestDefinition)
        ;
        $this->schema->expects($this->never())->method('getSchemes');
        $this->schema->expects($this->never())->method('getHost');

        $this->uriTemplate->expects($this->once())->method('expand')->with('/users', [])->willReturn('/users');

        $this->config['baseUri'] = 'https://example.org';
        $this->config['validateResponse'] = true;

        $service = $this->getApiService();
        $this->assertSame($this->schema, $service->getSchema());
        $promise = $service->callAsync('getUserCollection', ['foo' => 'bar']);
        $this->assertInstanceOf(Promise::class, $promise);
        $promise->wait();
    }

    private function getApiService(): ApiService
    {
        return new ApiService(
            $this->uriFactory,
            $this->uriTemplate,
            $this->httpClient,
            $this->messageFactory,
            $this->schema,
            $this->messageValidator,
            $this->serializer,
            $this->logger,
            $this->config
        );
    }
}
