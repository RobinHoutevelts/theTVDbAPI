<?php
/**
 * Copyright (c) 2017, Willem Van Iseghem (canihavesomecoffee) <theTVDbAPI@canihavesome.coffee>
 *
 * Permission to use, copy, modify, and/or distribute this software for any purpose with or without fee is hereby
 * granted, provided that the above copyright notice and this permission notice appear in all copies.
 *
 * THE SOFTWARE IS PROVIDED "AS IS" AND THE AUTHOR DISCLAIMS ALL WARRANTIES WITH REGARD TO THIS SOFTWARE INCLUDING ALL
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS. IN NO EVENT SHALL THE AUTHOR BE LIABLE FOR ANY SPECIAL, DIRECT,
 * INDIRECT, OR CONSEQUENTIAL DAMAGES OR ANY DAMAGES WHATSOEVER RESULTING FROM LOSS OF USE, DATA OR PROFITS, WHETHER
 * IN AN ACTION OF CONTRACT, NEGLIGENCE OR OTHER TORTIOUS ACTION, ARISING OUT OF OR IN CONNECTION WITH THE USE OR
 * PERFORMANCE OF THIS SOFTWARE.
 */

declare(strict_types = 1);

namespace CanIHaveSomeCoffee\TheTVDbAPI\Tests;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use InvalidArgumentException;
use CanIHaveSomeCoffee\TheTVDbAPI\Exception\ParseException;
use CanIHaveSomeCoffee\TheTVDbAPI\Exception\ResourceNotFoundException;
use CanIHaveSomeCoffee\TheTVDbAPI\Exception\UnauthorizedException;
use CanIHaveSomeCoffee\TheTVDbAPI\Route\AuthenticationRoute;
use CanIHaveSomeCoffee\TheTVDbAPI\Route\EpisodesRoute;
use CanIHaveSomeCoffee\TheTVDbAPI\Route\LanguagesRoute;
use CanIHaveSomeCoffee\TheTVDbAPI\Route\SearchRoute;
use CanIHaveSomeCoffee\TheTVDbAPI\Route\SeriesRoute;
use CanIHaveSomeCoffee\TheTVDbAPI\Route\UpdatesRoute;
use CanIHaveSomeCoffee\TheTVDbAPI\Route\UsersRoute;
use CanIHaveSomeCoffee\TheTVDbAPI\TheTVDbAPI;

/**
 * Class TheTVDbAPITest tests with mock objects, which do not require a live API key.
 *
 * @category TheTVDbAPI
 * @package  CanIHaveSomeCoffee\TheTVDbAPI\Tests
 * @author   Willem Van Iseghem (canihavesomecoffee) <theTVDbAPI@canihavesome.coffee>
 * @license  See start of document
 * @link     https://canihavesome.coffee/projects/theTVDbAPI
 */
class TheTVDbAPITest extends BaseUnitTest
{
    /**
     * @return Client
     */
    private function createClientWithMockHandler(array $queue)
    {
        $mock = new MockHandler($queue);
        return new Client(['handler' => HandlerStack::create($mock)]);
    }

    /**
     * @return Client
     */
    private function createPathErrorClient() : Client
    {
        return $this->createClientWithMockHandler([
            new Response(404, ['Content-Length' => 0]),
            new RequestException("Error Communicating with Server", new Request('GET', 'test'))
        ]);
    }

    /**
     * @return Client
     */
    private function createTokenErrorClient() : Client
    {
        return $this->createClientWithMockHandler([
            new Response(401, ['Content-Length' => 0]),
            new RequestException("Error Communicating with Server", new Request('GET', 'test'))
        ]);
    }

    public function testRouteInstanceTypes()
    {
        $instance = new TheTVDbAPI();

        static::assertInstanceOf(AuthenticationRoute::class, $instance->authentication());
        static::assertInstanceOf(EpisodesRoute::class, $instance->episodes());
        static::assertInstanceOf(LanguagesRoute::class, $instance->languages());
        static::assertInstanceOf(SearchRoute::class, $instance->search());
        static::assertInstanceOf(UpdatesRoute::class, $instance->updates());
        static::assertInstanceOf(UsersRoute::class, $instance->users());
        static::assertInstanceOf(SeriesRoute::class, $instance->series());
    }

    public function testInstanceInitialization()
    {
        $client = new Client(
            [
                'base_uri' => TheTVDbAPI::API_BASE_URI,
                'verify' => false,
                'headers' => ['Content-Type' => 'application/json']
            ]
        );
        $test_instance = new TheTVDbAPI($client);
        static::assertAttributeSame($client, 'httpClient', $test_instance);
    }

    public function testSetToken()
    {
        $token = "ABC";
        $test_instance = new TheTVDbAPI();
        $test_instance->setToken($token);
        static::assertAttributeSame($token, 'token', $test_instance);
    }

    public function setSetAcceptedLanguages()
    {
        $languages = ["nl", "en"];
        $test_instance = new TheTVDbAPI();
        $test_instance->setAcceptedLanguages($languages);
        static::assertAttributeSame($languages, 'languages', $test_instance);
    }

    public function testRetrieveAcceptedLanguages()
    {
        $languages = ["nl", "en"];
        $test_instance = new TheTVDbAPI();
        $test_instance->setAcceptedLanguages($languages);
        static::assertEquals($languages, $test_instance->getAcceptedLanguages());
    }

    public function testSetVersion()
    {
        $version = "1.33.7";
        $test_instance = new TheTVDbAPI();
        $test_instance->setVersion($version);
        static::assertAttributeSame($version, 'version', $test_instance);
        // Set wrong version
        static::expectException(InvalidArgumentException::class);
        static::expectExceptionMessage('Version does not match pattern x.y.z (where x, y, z are numbers)');
        $test_instance->setVersion('this.is.wrong');
    }

    public function testIfHeadersAreReturned()
    {
        $headers_expected = ['X-Foo' => ['Bar']];
        $mock = new MockHandler([
            new Response(200, $headers_expected),
            new RequestException("Error Communicating with Server", new Request('GET', 'test'))
        ]);

        $handler = HandlerStack::create($mock);
        $client = new Client(['handler' => $handler]);
        $test_instance = new TheTVDbAPI($client);
        $test_instance->setToken('ABC');

        $headers = $test_instance->requestHeaders('GET', '/');
        static::assertEquals($headers_expected, $headers);
    }

    public function testRequestWithMissingToken()
    {
        $client = $this->createTokenErrorClient();
        $test_instance = new TheTVDbAPI($client);
        $test_instance->setToken('ABC');

        static::expectException(UnauthorizedException::class);
        $test_instance->requestHeaders('GET', 'fail');
    }

    public function testRequestHeadersPathError()
    {
        $client = $this->createPathErrorClient();
        $test_instance = new TheTVDbAPI($client);
        $test_instance->setToken('ABC');

        // Not found
        static::expectException(ResourceNotFoundException::class);
        $test_instance->requestHeaders('GET', 'notfound');
    }

    public function testWorkingAPICall()
    {
        $input = "ABC";
        $client = $this->createClientWithMockHandler([
            new Response(200, [], $input),
            new RequestException("Error Communicating with Server", new Request('GET', 'test'))
        ]);
        $test_instance = new TheTVDbAPI($client);
        $test_instance->setToken('ABC');

        $response = $test_instance->performAPICall("GET", "/");
        static::assertEquals($input, $response->getBody()->getContents());
    }

    public function testAPICallWithMissingToken()
    {
        $client = $this->createTokenErrorClient();
        $test_instance = new TheTVDbAPI($client);
        $test_instance->setToken('ABC');

        static::expectException(UnauthorizedException::class);
        $test_instance->performAPICall('GET', 'fail');
    }

    public function testAPICallPathError()
    {
        $client = $this->createPathErrorClient();
        $test_instance = new TheTVDbAPI($client);
        $test_instance->setToken('ABC');

        // Not found
        static::expectException(ResourceNotFoundException::class);
        $test_instance->performAPICall('GET', 'notfound');
    }

    public function testAPICallJsonError()
    {
        $client = $this->createClientWithMockHandler([
            new Response(201, []),
            new RequestException("Error Communicating with Server", new Request('GET', 'test'))
        ]);
        $test_instance = new TheTVDbAPI($client);
        $test_instance->setToken('ABC');

        static::expectException(Exception::class);
        $test_instance->performAPICallWithJsonResponse('GET', 'fail');
    }

    public function testAPICallInvalidJson()
    {
        $input = "ABC";
        $client = $this->createClientWithMockHandler([
            new Response(200, [], $input),
            new RequestException("Error Communicating with Server", new Request('GET', 'test'))
        ]);
        $test_instance = new TheTVDbAPI($client);
        $test_instance->setToken('ABC');

        static::expectException(ParseException::class);
        $test_instance->performAPICallWithJsonResponse("GET", "/");
    }

    public function testAPICallWithIllFormedResponse()
    {
        $input = "{'a:'hello'}";
        $client = $this->createClientWithMockHandler([
            new Response(200, [], $input),
            new RequestException("Error Communicating with Server", new Request('GET', 'test'))
        ]);
        $test_instance = new TheTVDbAPI($client);
        $test_instance->setToken('ABC');

        static::expectException(ParseException::class);
        $test_instance->performAPICallWithJsonResponse("GET", "/");
    }

    public function testAPICallWithErrors()
    {
        $expected = [
            'foo' => 'bar',
            'errors' => [
                'invalidLanguage' => 'not found',
                'invalidQueryParams' => 'invalid param a',
                'invalidFilters' => 'invalid filter b'
            ]
        ];
        $client = $this->createClientWithMockHandler([
            new Response(200, [], json_encode($expected)),
            new RequestException("Error Communicating with Server", new Request('GET', 'test'))
        ]);
        $test_instance = new TheTVDbAPI($client);
        $test_instance->setToken('ABC');

        $result = $test_instance->performAPICallWithJsonResponse("GET", "/");
        $errors = $test_instance->getLastJSONErrors();
        static::assertCount(3, $errors);
        static::assertEquals($expected, $result);
    }

    public function testAPICallWithLinkData()
    {
        $expected = [
            'foo' => 'bar',
            'links' => ['previous' => 0, 'next' => 2, 'first' => 0, 'last' => 1337]
        ];
        $client = $this->createClientWithMockHandler([
            new Response(200, [], json_encode($expected)),
            new RequestException("Error Communicating with Server", new Request('GET', 'test'))
        ]);
        $test_instance = new TheTVDbAPI($client);
        $test_instance->setToken('ABC');

        $result = $test_instance->performAPICallWithJsonResponse("GET", "/");
        $links = $test_instance->getLastLinks();
        static::assertCount(4, $links);
        static::assertEquals($expected, $result);
    }

    public function testAPICallReturningJson()
    {
        $expected = ['foo' => 'bar', 'baz' => 'foobar'];

        $client = $this->createClientWithMockHandler([
            new Response(200, [], json_encode($expected)),
            new RequestException("Error Communicating with Server", new Request('GET', 'test'))
        ]);
        $test_instance = new TheTVDbAPI($client);
        $test_instance->setToken('ABC');

        $result = $test_instance->performAPICallWithJsonResponse("GET", "/");
        static::assertEquals($expected, $result);
    }

    public function testAPICallReturnDataJson()
    {
        $expected = ['foo' => 'bar', 'data' => ['barfoo' => 'foobar']];
        $client = $this->createClientWithMockHandler([
            new Response(200, [], json_encode($expected)),
            new RequestException("Error Communicating with Server", new Request('GET', 'test'))
        ]);
        $test_instance = new TheTVDbAPI($client);
        $test_instance->setToken('ABC');

        $result = $test_instance->performAPICallWithJsonResponse("GET", "/");
        static::assertEquals($expected['data'], $result);
    }
}
