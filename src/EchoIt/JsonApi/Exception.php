<?php namespace EchoIt\JsonApi;

/**
 * JsonApi\Exception represents an Exception that can be thrown where a JSON response may be expected.
 *
 * @author Ronni Egeriis Persson <ronni@egeriis.me>
 */
class Exception extends \Exception
{
    protected $httpStatusCode;
    protected $additionalAttrs;

    /**
     * Constructor.
     *
     * @param string  $message        The Exception message to throw
     * @param int     $code           The Exception code
     * @param int     $httpStatusCode HTTP status code which can be used for broken request
     * @param array   $additionalAttrs
     */
    public function __construct($message = '', $code = 0, $httpStatusCode = 500, array $additionalAttrs = array())
    {
        parent::__construct($message, $code);

        $this->httpStatusCode = $httpStatusCode;
        $this->additionalAttrs = $additionalAttrs;
    }

    /**
     * This method returns a HTTP response representation of the Exception
     *
     * @return \EchoIt\JsonApi\ErrorResponse
     */
    public function response()
    {
        return new ErrorResponse($this->httpStatusCode, $this->code, $this->message, $this->additionalAttrs);
    }
}
