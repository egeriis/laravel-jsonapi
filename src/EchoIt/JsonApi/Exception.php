<?php namespace EchoIt\JsonApi;

/**
 * JsonApi\Exception represents an Exception that can be thrown where a JSON response may be expected.
 *
 * @author Ronni Egeriis Persson <ronni@egeriis.me>
 */
class Exception extends \Exception
{
    protected $httpStatusCode;

    /**
     * Constructor.
     *
     * @param string  $message        The Exception message to throw
     * @param int     $code           The Exception code
     * @param int     $httpStatusCode HTTP status code which can be used for broken request
     */
    public function __construct($message = '', $code = 0, $httpStatusCode = 500)
    {
        parent::__construct($message, $code);

        $this->httpStatusCode = $httpStatusCode;
    }

    /**
     * This method returns a HTTP response representation of the Exception
     *
     * @return JsonApi\ErrorResponse
     */
    public function response()
    {
        return new ErrorResponse($this->httpStatusCode, $this->code, $this->message);
    }
}
