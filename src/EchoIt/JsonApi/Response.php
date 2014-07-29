<?php namespace EchoIt\JsonApi;

use Illuminate\Http\JsonResponse;

/**
 * This class contains the parameters to return in the response to an API request.
 */
class Response
{
    /**
     * An array of parameters.
     *
     * @var array
     */
    protected $responseData = [];

    /**
     * The main response.
     *
     * @var array|object
     */
    protected $body;

    /**
     * Constructor
     * @param array|object $body
     */
    public function __construct($body)
    {
        $this->body = $body;
    }

    /**
     * Used to set or overwrite a parameter.
     *
     * @param string $key
     * @param mixed  $value
     */
    public function __set($key, $value)
    {
        if ($key == 'body') {
            $this->body = $value;
            return;
        }
        $this->responseData[$key] = $value;
    }

    /**
     * Returns a JsonResponse with the set parameters and body.
     *
     * @param  string $bodyKey The key on which to set the main response.
     * @return Illuminate\Http\JsonResponse
     */
    public function toJsonResponse($bodyKey = 'data', $options = 0)
    {
        return new JsonResponse(array_merge(
            [ $bodyKey => $this->body ],
            array_filter($this->responseData)
        ), 200, [], $options);
    }
}
