<?php

namespace ByJG\ApiTools\Base;

use ByJG\ApiTools\AbstractRequester;
use ByJG\ApiTools\Base\Schema;
use ByJG\ApiTools\Exception\DefinitionNotFoundException;
use ByJG\ApiTools\Exception\HttpMethodNotFoundException;
use ByJG\ApiTools\Exception\InvalidDefinitionException;
use ByJG\ApiTools\Exception\NotMatchedException;
use ByJG\ApiTools\Exception\PathNotFoundException;
use ByJG\ApiTools\Exception\StatusCodeNotMatchedException;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Utility trait to implement tests
 *
 * Use this trait to integrate request/response testing into your PHPUnit-based test case.
 *
 * Usage:
 * class MyTest extends TestCase
 * {
 *     use RequestTestTrait;
 *
 *     public static function setUpBeforeClass()
 *     {
 *         parent::setUpBeforeClass();
 *         $schema = Schema::getInstance(file_get_contents('path/to/schema.json'));
 *         self::setSchema($schema);
 *     }
 *
 *     public function test()
 *     {
 *         $request = new ApiRequester();
 *         $request->withMethod('GET')
 *             ->withPath('/status')
 *             ->assertResponseCode(200);
 *         $this->assertRequest($request);
 *     }
 * }
 */
trait RequestTestTrait
{
    /** @var Schema */
    private static $schema;

    /**
     * Initialize schema
     *
     * Call this function from e.g. setUpBeforeClass(). This is a convenience
     * function. Without setting a schema, every request must have its schema
     * set.
     */
    protected static function setSchema(?Schema $schema): void
    {
        static::$schema = $schema;
    }

    /**
     * @param AbstractRequester $request
     * @return mixed
     * @throws DefinitionNotFoundException
     * @throws HttpMethodNotFoundException
     * @throws InvalidDefinitionException
     * @throws NotMatchedException
     * @throws PathNotFoundException
     * @throws StatusCodeNotMatchedException
     * @throws GuzzleException
     */
    public function assertRequest(AbstractRequester $request)
    {
        // Add own schema if nothing is passed.
        if (!$request->hasSchema()) {
            $request->withSchema(static::$schema);
        }

        // Request based on the Swagger Request definitions
        $body = $request->send();

        // Note:
        // This code is only reached if the send is successful and all
        // matches are satisfied. Otherwise, an error is thrown before
        // reaching this.
        $this->assertTrue(true);

        return $body;
    }
}
