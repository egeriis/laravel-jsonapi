<?php

use EchoIt\JsonApi\ErrorResponse;

class ErrorResponseTest extends PHPUnit_Framework_TestCase
{
    public function testResponseHeaders()
    {
        $res = new ErrorResponse(404, 100, 'An error occurred');

        $this->assertEquals(404, $res->getStatusCode());
        $this->assertEquals('application/json', $res->headers->get('Content-Type'));
    }

    public function testResponseBody()
    {
        $res = new ErrorResponse(404, 100, 'An error occurred');
        $this->assertEquals('{"errors":[{"status":404,"code":100,"title":"An error occurred"}]}', $res->getContent());
    }

    public function testResponseWithAdditionalAttrs()
    {
        $res = new ErrorResponse(404, 100, 'An error occurred', [
            'stacktrace' => [
                'line' => 100,
                'file' => 'script.php'
            ]
        ]);
        $this->assertEquals('{"errors":[{"status":404,"code":100,"title":"An error occurred","stacktrace":{"line":100,"file":"script.php"}}]}', $res->getContent());
    }
}
