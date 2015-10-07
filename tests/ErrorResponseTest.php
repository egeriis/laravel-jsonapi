<?php

use EchoIt\JsonApi\ErrorResponse;
use EchoIt\JsonApi\Tests\JsonSchemaValidationTrait;

class ErrorResponseTest extends PHPUnit_Framework_TestCase
{
    use JsonSchemaValidationTrait;

    public function testResponseHeaders()
    {
        $res = new ErrorResponse(404, 100, 'An error occurred');

        $this->assertEquals(404, $res->getStatusCode());
        $this->assertEquals('application/json', $res->headers->get('Content-Type'));
        $this->assertJsonapiValid($res->getData());
    }

    public function testResponseBody()
    {
        $res = new ErrorResponse(404, 100, 'An error occurred');
        $this->assertEquals('{"errors":[{"status":"404","code":"100","title":"An error occurred"}]}', $res->getContent());
        $this->assertJsonapiValid($res->getData());
    }

    public function testResponseWithAdditionalAttrs()
    {
        $res = new ErrorResponse(404, 100, 'An error occurred', [
            'meta' => [
                'stacktrace' => [
                    'line' => 100,
                    'file' => 'script.php'
                ],
            ],
        ]);
        $this->assertEquals('{"errors":[{"status":"404","code":"100","title":"An error occurred","meta":{"stacktrace":{"line":100,"file":"script.php"}}}]}', $res->getContent());
        $this->assertJsonapiValid($res->getData());
    }
}
