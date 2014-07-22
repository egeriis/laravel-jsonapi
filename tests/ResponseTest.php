<?php

use EchoIt\JsonApi\Response;

class ResponseTest extends PHPUnit_Framework_TestCase
{
    public function testResponseWithUnnamedBodyOnly()
    {
        $res = new Response([
            'value' => 1
        ]);

        $json = $res->toJsonResponse();
        $this->assertInstanceOf('Illuminate\\Http\\JsonResponse', $json);

        $data = $json->getData();

        $this->assertObjectHasAttribute('data', $data);
        $this->assertInstanceOf('StdClass', $data->data);
    }

    public function testResponseWithNamedBodyOnly()
    {
        $res = new Response([
            'value' => 1
        ]);

        $json = $res->toJsonResponse('body');
        $this->assertInstanceOf('Illuminate\\Http\\JsonResponse', $json);

        $data = $json->getData();

        $this->assertObjectHasAttribute('body', $data);
        $this->assertInstanceOf('StdClass', $data->body);
    }

    public function testResponseWithParams()
    {
        $res = new Response([
            'value' => 1
        ]);
        $res->links = [
            [ 'id' => 1 ],
            [ 'id' => 2 ]
        ];

        $json = $res->toJsonResponse();
        $data = $json->getData();

        $this->assertObjectHasAttribute('links', $data);
        $this->assertInternalType('array', $data->links);
        $this->assertCount(2, $data->links);
        $this->assertInstanceOf('StdClass', $data->links[0]);
        $this->assertObjectHasAttribute('id', $data->links[0]);
    }

    public function testResponseParamAndBodyOrder()
    {
        $res = new Response([ 'value' => 1 ]);
        $res->links = [ [ 'id' => 1 ], [ 'id' => 2 ] ];
        $res->errors = [ [ 'message' => 'Unknown error' ] ];

        $json = $res->toJsonResponse();
        $data = $json->getData();

        $this->assertObjectHasAttribute('data', $data);
        $this->assertObjectHasAttribute('links', $data);
        $this->assertObjectHasAttribute('errors', $data);

        $this->assertNotEquals(['links', 'errors', 'data'], array_keys(get_object_vars($data)));
        $this->assertEquals(['data', 'links', 'errors'], array_keys(get_object_vars($data)));
    }
}
