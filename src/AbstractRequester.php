<?php

namespace ByJG\ApiTools;

use ByJG\ApiTools\Base\Schema;
use ByJG\ApiTools\Exception\NotMatchedException;
use ByJG\ApiTools\Exception\StatusCodeNotMatchedException;
use Exception;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Abstract baseclass for request handlers.
 *
 * The baseclass provides processing and verification of request and response.
 * It only delegates the actual message exchange to the derived class. For the
 * messages, it uses the PSR-7 implementation from Guzzle.
 *
 * This is an implementation of the Template Method Patttern
 * (https://en.wikipedia.org/wiki/Template_method_pattern).
 */
abstract class AbstractRequester
{
    protected $method;
    protected $path;
    protected $requestHeader = [];
    protected $query = [];
    protected $requestBody = null;
    /**
     * @var Schema
     */
    protected $schema = null;

    protected $statusExpected = 200;
    protected $assertHeader = [];

    public function __construct()
    {
    }

    /**
     * abstract function to be implemented by derived classes
     *
     * This function must be implemented by derived classes. It should process
     * the given request and return an according response.
     *
     * @param RequestInterface $request
     * @return ResponseInterface
     */
    abstract protected function handleRequest(RequestInterface $request);

    /**
     * @param Schema $schema
     * @return $this
     */
    public function withSchema($schema)
    {
        $this->schema = $schema;

        return $this;
    }

    /**
     * @return bool
     */
    public function hasSchema()
    {
        return !empty($this->schema);
    }

    /**
     * @param string $method
     * @return $this
     */
    public function withMethod($method)
    {
        $this->method = $method;

        return $this;
    }

    /**
     * @param string $path
     * @return $this
     */
    public function withPath($path)
    {
        $this->path = $path;

        return $this;
    }

    /**
     * add query parameter(s)
     *
     * @param string $name
     * @param string|string[] $value
     * @return $this
     */
    public function withQueryParameter($name, $value)
    {
        // normalize `$value` to an array with 1-N values
        if (!is_array($value)) {
            $value = [$value];
        }

        // combine with existing query parameters
        if (isset($this->query[$name])) {
            $value = $this->query[$name] + $value;
        }

        $this->query[$name] = $value;

        return $this;
    }

    /**
     * @param array $requestHeader
     * @return $this
     */
    public function withRequestHeader($requestHeader)
    {
        if (is_null($requestHeader)) {
            $this->requestHeader = [];
            return $this;
        }

        $this->requestHeader = array_merge($this->requestHeader, $requestHeader);

        return $this;
    }

    /**
     * @deprecated Use withQueryParameter() instead.
     * @param array $query
     * @return $this
     */
    public function withQuery($query)
    {
        if (is_null($query)) {
            $this->query = [];
            return $this;
        }

        foreach ($query as $name => $value) {
            $this->withQueryParameter($name, $value);
        }

        return $this;
    }

    /**
     * @param null $requestBody
     * @return $this
     */
    public function withRequestBody($requestBody)
    {
        $this->requestBody = $requestBody;

        return $this;
    }

    public function assertResponseCode($code)
    {
        $this->statusExpected = $code;

        return $this;
    }

    public function assertHeaderContains($header, $contains)
    {
        $this->assertHeader[$header] = $contains;

        return $this;
    }

    /**
     * @return mixed
     * @throws Exception\DefinitionNotFoundException
     * @throws Exception\HttpMethodNotFoundException
     * @throws Exception\InvalidDefinitionException
     * @throws Exception\PathNotFoundException
     * @throws GuzzleException
     * @throws NotMatchedException
     * @throws StatusCodeNotMatchedException
     */
    public function send()
    {
        $this->validateRequest();

        // Preparing Parameters
        $paramInQuery = null;
        if (!empty($this->query)) {
            $paramInQuery = '?' . http_build_query($this->query);
        }

        // Preparing Header
        if (empty($this->requestHeader)) {
            $this->requestHeader = [];
        }
        $header = array_merge(
            [
                'Accept' => 'application/json'
            ],
            $this->requestHeader
        );

        // Defining Variables
        $serverUrl = $this->schema->getServerUrl();
        $basePath = $this->schema->getBasePath();
        $pathName = $this->path;

        // Check if the body is the expected before request
        $bodyRequestDef = $this->schema->getRequestParameters("$basePath$pathName", $this->method);
        $bodyRequestDef->match($this->requestBody);

        // Make the request
        $request = new Request(
            $this->method,
            $serverUrl . $pathName . $paramInQuery,
            $header,
            json_encode($this->requestBody)
        );

        $statusReturned = null;
        try {
            $response = $this->handleRequest($request);
            $responseHeader = $response->getHeaders();
            $responseBody = json_decode((string) $response->getBody(), true);
            $statusReturned = $response->getStatusCode();
        } catch (BadResponseException $ex) {
            $responseHeader = $ex->getResponse()->getHeaders();
            $responseBody = json_decode((string) $ex->getResponse()->getBody(), true);
            $statusReturned = $ex->getResponse()->getStatusCode();
        }

        // Assert results
        if ($this->statusExpected != $statusReturned) {
            throw new StatusCodeNotMatchedException(
                "Status code not matched: Expected {$this->statusExpected}, got {$statusReturned}",
                $responseBody
            );
        }

        $bodyResponseDef = $this->schema->getResponseParameters(
            "$basePath$pathName",
            $this->method,
            $this->statusExpected
        );
        $bodyResponseDef->match($responseBody);

        if (count($this->assertHeader) > 0) {
            foreach ($this->assertHeader as $key => $value) {
                if (!isset($responseHeader[$key]) || strpos($responseHeader[$key][0], $value) === false) {
                    throw new NotMatchedException(
                        "Does not exists header '$key' with value '$value'",
                        $responseHeader
                    );
                }
            }
        }

        return $responseBody;
    }

    /**
     * utility function to validate request data
     *
     * Note that this is incomplete still:
     *  - not all paths are valid
     *  - not all query parameters are valid
     */
    private function validateRequest()
    {
        // validate schema
        if ($this->schema === null) {
            throw new Exception('no schema set');
        }
        if (!($this->schema instanceof Schema)) {
            throw new Exception('invalid schema set');
        }

        // validate method
        if ($this->method === null) {
            throw new Exception('no method set');
        }
        $validMethods = [
            'CONNECT',
            'DELETE',
            'GET',
            'HEAD',
            'OPTIONS',
            'PATCH',
            'POST',
            'PUT',
            'TRACE',
        ];
        if (!in_array($this->method, $validMethods, true)) {
            throw new Exception('invalid method set');
        }

        // validate path
        if ($this->path === null) {
            throw new Exception('no path set');
        }
        if (!is_string($this->path)) {
            throw new Exception('invalid path set');
        }
        if ($this->path !== '') {
            if ($this->path[0] !== '/') {
                throw new Exception('path must be empty or begin with a slash');
            }
            if (strstr($this->path, '?') !== false) {
                throw new Exception('path contains query parameters');
            }
            if (strstr($this->path, '#') !== false) {
                throw new Exception('path contains fragment identifier');
            }
        }

        // validate query parameters
        foreach ($this->query as $name => $values) {
            if (!is_string($name)) {
                throw new Exception('names of query parameters must be strings');
            }
            foreach ($values as $value) {
                if (!is_string($value)) {
                    throw new Exception('values of query parameters must be strings');
                }
            }
        }
    }
}
