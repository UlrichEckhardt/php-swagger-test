<?php

use ByJG\ApiTools\AbstractRequester;
use ByJG\ApiTools\Base\Body;
use ByJG\ApiTools\Base\Schema;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class AbstractRequesterTest extends TestCase
{
    private const DEFAULT_METHOD = 'POST';
    public const DEFAULT_SERVER_URL = 'https://example.com';
    public const DEFAULT_BASE_PATH = '/v1';
    private const DEFAULT_PATH = '/';

    /** @var MockObject|AbstractRequester */
    private $requester;

    /** @var MockObject|Schema */
    private $schema;

    protected function setUp()
    {
        parent::setUp();

        $this->requester = $this->getMockForAbstractClass(AbstractRequester::class);

        $this->schema = $this->getMockBuilder(Schema::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    public function testDefault()
    {
        // request body part of the schema
        $requestBody = $this->getMockBuilder(Body::class)
            ->disableOriginalConstructor()
            ->getMock();
        $requestBody->expects($this->once())
            ->method('match')
            ->with(null);

        // response body part of the schema
        $responseBody = $this->getMockBuilder(Body::class)
            ->disableOriginalConstructor()
            ->getMock();
        $responseBody->expects($this->once())
            ->method('match')
            ->with(null);

        // set up schema
        $this->schema->method('getServerUrl')
            ->willReturn(self::DEFAULT_SERVER_URL);
        $this->schema->method('getBasePath')
            ->willReturn(self::DEFAULT_BASE_PATH);
        $this->schema->method('getRequestParameters')
            ->with(
                self::DEFAULT_BASE_PATH . self::DEFAULT_PATH,
                self::DEFAULT_METHOD
            )
            ->willReturn($requestBody);
        $this->schema->method('getResponseParameters')
            ->with(
                self::DEFAULT_BASE_PATH . self::DEFAULT_PATH,
                self::DEFAULT_METHOD,
                200
            )
            ->willReturn($responseBody);

        // set up abstract function to validate the request being sent
        $this->requester->expects($this->once())
            ->method('handleRequest')
            ->with($this->isInstanceOf(Request::class))
            /** @var Request $request */
            ->willReturnCallback(function ($request) {
                // validate headers
                $headers = $request->getHeaders();
                $this->assertEquals($headers['Host'], ['example.com']);
                $this->assertEquals($headers['Accept'], ['application/json']);
                // validate method
                $this->assertEquals(self::DEFAULT_METHOD, $request->getMethod());
                // validate URI
                $uri = $request->getUri();
                $this->assertEquals('https', $uri->getScheme());
                $this->assertEquals('', $uri->getUserInfo());
                $this->assertEquals('example.com', $uri->getHost());
                $this->assertEquals(self::DEFAULT_PATH, $uri->getPath());
                $this->assertEquals('id=42', $uri->getQuery());
                $this->assertEquals('', $uri->getFragment());

                return new Response(200);
            });

        $this->requester->withSchema($this->schema);
        $this->requester->withMethod(self::DEFAULT_METHOD);
        $this->requester->withPath(self::DEFAULT_PATH);
        $this->requester->withQuery(['id' => 42]);

        $res = $this->requester->send();

        $this->assertNull($res);
    }
}
